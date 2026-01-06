"""
Database operations for forecast reports.
Mirrors the structure expected by the original R scripts while using
real-time Supabase data.
"""

import sys
from collections import defaultdict
from datetime import datetime
from typing import Dict, List, Optional, Tuple

import requests

from config import BLOOD_TYPES, SUPABASE_API_KEY, SUPABASE_URL


class DatabaseConnection:
    """Handles database connections and queries."""

    def __init__(self):
        self.supabase_url = SUPABASE_URL
        self.api_key = SUPABASE_API_KEY
        self.connection_active = False

    # ------------------------------------------------------------------ #
    # Connection utilities
    # ------------------------------------------------------------------ #
    def connect(self) -> bool:
        """Connect to Supabase database."""
        try:
            headers = {
                "apikey": self.api_key,
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json",
            }
            test_url = f"{self.supabase_url}/rest/v1/"
            response = requests.get(test_url, headers=headers, timeout=10)
            if response.status_code in (200, 400):
                self.connection_active = True
                return True
            return False
        except Exception as exc:  # pragma: no cover - network call
            print(f"Database connection error: {exc}", file=sys.stderr)
            self.connection_active = False
            return False

    def disconnect(self):
        """Disconnect from database."""
        self.connection_active = False

    # ------------------------------------------------------------------ #
    # Raw REST access
    # ------------------------------------------------------------------ #
    def supabase_request(self, endpoint: str, limit: int = 5000) -> List[Dict]:
        """
        Make Supabase requests with pagination support (real-time).
        """
        all_data = []
        offset = 0

        while True:
            url = f"{self.supabase_url}/rest/v1/{endpoint}"
            if "?" in endpoint:
                url += f"&limit={limit}&offset={offset}"
            else:
                url += f"?limit={limit}&offset={offset}"

            headers = {
                "apikey": self.api_key,
                "Authorization": f"Bearer {self.api_key}",
                "Content-Type": "application/json",
                "Accept-Encoding": "gzip, deflate",
                "Cache-Control": "no-cache, no-store, must-revalidate",
                "Pragma": "no-cache",
                "Expires": "0",
                "Prefer": "return=representation",
            }

            try:
                response = requests.get(url, headers=headers, timeout=60)
                if response.status_code != 200:
                    error_msg = (
                        response.text[:1000]
                        if hasattr(response, "text")
                        else str(response.content[:1000])
                    )
                    print(f"ERROR: Supabase request failed ({response.status_code})", file=sys.stderr)
                    print(f"  - Endpoint: {endpoint}", file=sys.stderr)
                    print(f"  - URL (truncated): {url[:200]}...", file=sys.stderr)
                    print(f"  - Error response: {error_msg}", file=sys.stderr)
                    raise Exception(error_msg)

                data = response.json()
                if not isinstance(data, list):
                    print(f"WARNING: Supabase returned non-list data: {type(data)}", file=sys.stderr)
                    data = []

                all_data.extend(data)

                if len(data) < limit:
                    break
                offset += limit
            except Exception as exc:  # pragma: no cover - network call
                print(f"Error in supabase_request: {exc}", file=sys.stderr)
                break

        return all_data

    # ------------------------------------------------------------------ #
    # Raw tables
    # ------------------------------------------------------------------ #
    def fetch_blood_units(self) -> List[Dict]:
        """
        Fetch all blood bank units from Supabase.
        """
        endpoint = (
            "blood_bank_units?"
            "select=unit_id,unit_serial_number,blood_collection_id,donor_id,"
            "blood_type,collected_at,created_at,status,handed_over_at,expires_at,"
            "disposed_at,disposition_reason,hospital_from,request_id,is_check"
            "&order=collected_at.asc"
        )
        return self.supabase_request(endpoint)

    def fetch_blood_requests(self) -> List[Dict]:
        """
        Fetch all hospital requests from Supabase.
        """
        endpoint = (
            "blood_requests?"
            "select=request_id,user_id,patient_name,patient_age,patient_gender,"
            "patient_diagnosis,patient_blood_type,rh_factor,units_requested,"
            "is_asap,blood_component,hospital_admitted,physician_name,status,"
            "requested_on,approved_date,handed_over_date,decline_reason,"
            "approved_by,handed_over_by"
            "&order=requested_on.asc"
        )
        return self.supabase_request(endpoint)

    # ------------------------------------------------------------------ #
    # Helpers mirroring R's aggregation logic
    # ------------------------------------------------------------------ #
    @staticmethod
    def _normalize_month(date_str: Optional[str]) -> Optional[str]:
        if not date_str:
            return None
        try:
            date = datetime.fromisoformat(date_str.replace("Z", "+00:00"))
            if date.tzinfo:
                date = date.replace(tzinfo=None)
            return date.replace(day=1, hour=0, minute=0, second=0, microsecond=0).strftime("%Y-%m-%d")
        except ValueError:
            return None

    def _aggregate_monthly_counts(
        self, records: List[Dict], date_key: str, blood_key: str, value_key: Optional[str] = None
    ) -> Dict[str, Dict[str, Dict[str, float]]]:
        monthly = defaultdict(lambda: defaultdict(lambda: defaultdict(float)))

        for record in records:
            month_key = self._normalize_month(record.get(date_key))
            blood_type = record.get(blood_key)
            if not month_key or not blood_type:
                continue

            value = record.get(value_key, 1) if value_key else 1
            try:
                value = float(value)
            except (TypeError, ValueError):
                value = 0.0

            monthly[month_key][blood_type]["count"] += 1
            monthly[month_key][blood_type]["value"] += value

        return monthly

    def get_monthly_donations(self) -> List[Dict]:
        """
        Equivalent of R's `df_monthly_donations` using Supabase data.
        """
        blood_units = self.fetch_blood_units()
        monthly = self._aggregate_monthly_counts(
            blood_units, date_key="collected_at", blood_key="blood_type"
        )

        rows: List[Dict] = []
        for month in sorted(monthly.keys()):
            for blood_type in BLOOD_TYPES:
                count = int(monthly[month].get(blood_type, {}).get("count", 0))
                if count <= 0:
                    continue
                rows.append(
                    {
                        "month": month,
                        "blood_type": blood_type,
                        "units_collected": count,
                    }
                )
        return rows

    def get_monthly_requests(self) -> List[Dict]:
        """
        Equivalent of R's `df_monthly_requests` using Supabase data.
        """
        blood_requests = self.fetch_blood_requests()
        monthly = defaultdict(lambda: defaultdict(lambda: {"total_requests": 0, "units_requested": 0.0, "asap_requests": 0}))

        for request in blood_requests:
            month_key = self._normalize_month(request.get("requested_on"))
            blood_type = request.get("patient_blood_type")
            if not month_key or not blood_type:
                continue

            try:
                units = float(request.get("units_requested", 0) or 0)
            except (TypeError, ValueError):
                units = 0.0

            monthly[month_key][blood_type]["total_requests"] += 1
            monthly[month_key][blood_type]["units_requested"] += units
            monthly[month_key][blood_type]["asap_requests"] += 1 if request.get("is_asap") else 0

        rows: List[Dict] = []
        for month in sorted(monthly.keys()):
            for blood_type in BLOOD_TYPES:
                metrics = monthly[month].get(blood_type)
                if not metrics:
                    continue
                rows.append(
                    {
                        "month": month,
                        "blood_type": blood_type,
                        "total_requests": metrics["total_requests"],
                        "units_requested": int(round(metrics["units_requested"])),
                        "asap_requests": metrics["asap_requests"],
                    }
                )
        return rows

    def get_structured_datasets(self) -> Tuple[List[Dict], List[Dict]]:
        """
        Returns donation and request datasets matching the schema
        expected by the R scripts (df_donations, df_requests).
        """
        donations = self.fetch_blood_units()
        requests = self.fetch_blood_requests()
        return donations, requests
