"""
Reports Dashboard - Donor & Hospital Overview
=============================================

Generates a JSON payload (and refreshes interactive HTML charts) for the
admin "Reports" dashboard. This focuses on:

- Donor KPIs (active, eligible today)
- Inventory KPIs (available units, nearing expiry)
- Hospital KPIs (requests today)
- Donor demographic charts
- Donation activity / channels / outcomes charts
- Hospital request pattern charts

This module is designed to be invoked from PHP via a small API wrapper,
similar to the existing forecast dashboard integration.
"""

from __future__ import annotations

import json
import sys
from datetime import datetime
from pathlib import Path
from typing import Any, Dict, List

from database import DatabaseConnection

BASE_DIR = Path(__file__).parent
CHARTS_DIR = BASE_DIR / "charts"


def _load_module(module_name: str, filename: str):
    """
    Dynamically load a Python module by filename, even if it contains spaces.
    """
    import importlib.util

    path = BASE_DIR / filename
    spec = importlib.util.spec_from_file_location(module_name, path)
    if spec is None or spec.loader is None:
        raise ImportError(f"Could not load spec for {filename}")
    module = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(module)  # type: ignore[call-arg]
    return module


def _safe_call(func, *args, **kwargs) -> Dict[str, Any]:
    """
    Call a function and wrap any exception into a structured error dict.
    Always returns a dict.
    """
    try:
        result = func(*args, **kwargs)
        # If the function already returns a dict-like object, just pass it through
        if isinstance(result, dict):
            return result
        return {"success": True, "value": result}
    except Exception as exc:  # pragma: no cover - defensive
        print(f"[reports_dashboard_overview] Error in {func.__name__}: {exc}", file=sys.stderr)
        return {"success": False, "error": str(exc)}


def _ensure_charts_dir():
    CHARTS_DIR.mkdir(parents=True, exist_ok=True)


def _fetch_blood_units() -> List[Dict[str, Any]]:
    """Fetch all blood_bank_units rows from Supabase."""
    db = DatabaseConnection()
    blood_units: List[Dict[str, Any]] = []
    if db.connect():
        try:
            blood_units = db.fetch_blood_units()
        finally:
            db.disconnect()
    return blood_units


def _classify_unit_status(unit: Dict[str, Any]) -> str:
    """
    Map raw blood_bank_units status fields into the canonical status values
    used in the system:
      - "Valid"
      - "Buffer"
      - "disposed"
      - "handed_over"

    We preserve the exact casing used in the database so the report mirrors
    the real data.
    """
    raw_status = (unit.get("status") or "").strip()

    # If status is already one of the canonical values, use it as-is
    if raw_status in {"Valid", "Buffer", "disposed", "handed_over"}:
        return raw_status

    # Fallback inference based on timestamps if explicit status is missing
    if unit.get("handed_over_at"):
        return "handed_over"
    if unit.get("disposed_at"):
        return "disposed"

    # Default to Valid when nothing else is specified
    return "Valid"


def _aggregate_units_collected_by_status() -> Dict[str, Any]:
    """
    Build a month-level summary of units collected and their status breakdown
    from blood_bank_units:
      - Uses collected_at month
      - Groups counts by status (Valid / Handed Over / Disposed / Other)
    """
    from datetime import datetime as _dt

    blood_units = _fetch_blood_units()

    by_month: Dict[str, Dict[str, int]] = {}

    for unit in blood_units:
        collected_at = unit.get("collected_at") or unit.get("created_at")
        if not collected_at:
            continue
        try:
            text = str(collected_at)
            if "Z" in text:
                dt = _dt.fromisoformat(text.replace("Z", "+00:00"))
            else:
                dt = _dt.fromisoformat(text)
        except Exception:
            continue

        if dt.tzinfo:
            dt = dt.replace(tzinfo=None)

        month_key = dt.replace(day=1, hour=0, minute=0, second=0, microsecond=0).strftime(
            "%Y-%m-%d"
        )
        bucket = by_month.setdefault(
            month_key,
            {"Valid": 0, "Buffer": 0, "handed_over": 0, "disposed": 0},
        )
        status_bucket = _classify_unit_status(unit)
        if status_bucket not in bucket:
            # Ignore completely unknown statuses for this summary
            continue
        bucket[status_bucket] += 1

    rows: List[Dict[str, Any]] = []
    for month in sorted(by_month.keys()):
        counts = by_month[month]
        total = sum(counts.values())
        rows.append(
            {
                "month": month,
                "total_collected": total,
                "valid": counts.get("Valid", 0),
                "buffer": counts.get("Buffer", 0),
                "handed_over": counts.get("handed_over", 0),
                "disposed": counts.get("disposed", 0),
            }
        )

    return {"success": True, "data": rows}


def _aggregate_units_allocated() -> Dict[str, Any]:
    """
    Units allocated to hospitals from blood_bank_units:
      - unit_serial_number
      - blood_type
      - hospital_from (non-null)
      - handed_over_at (date part)
    Grouped per year of handed_over_at.
    """
    units = _fetch_blood_units()
    per_year: Dict[str, List[Dict[str, Any]]] = {}

    for unit in units:
        hospital = (unit.get("hospital_from") or "").strip()
        handed = unit.get("handed_over_at")
        if not hospital or not handed:
            continue

        try:
            text = str(handed)
            if "Z" in text:
                dt = datetime.fromisoformat(text.replace("Z", "+00:00"))
            else:
                dt = datetime.fromisoformat(text)
        except Exception:
            continue

        if dt.tzinfo:
            dt = dt.replace(tzinfo=None)

        year = str(dt.year)
        date_alloc = dt.date().isoformat()
        per_year.setdefault(year, []).append(
            {
                "serial_number": unit.get("unit_serial_number"),
                "blood_type": unit.get("blood_type"),
                "hospital": hospital,
                "date_allocated": date_alloc,
            }
        )

    return {"success": True, "per_year": per_year}


def _aggregate_units_expired() -> Dict[str, Any]:
    """
    Units expired (disposed) from blood_bank_units:
      - use _classify_unit_status == "Disposed"
      - collected_at (date)
      - expires_at (date)
    Grouped per year of expires_at.
    """
    units = _fetch_blood_units()
    per_year: Dict[str, List[Dict[str, Any]]] = {}

    for unit in units:
        if _classify_unit_status(unit) != "disposed":
            continue

        collected = unit.get("collected_at")
        expires = unit.get("expires_at")
        if not collected or not expires:
            continue

        try:
            c_text = str(collected)
            if "Z" in c_text:
                c_dt = datetime.fromisoformat(c_text.replace("Z", "+00:00"))
            else:
                c_dt = datetime.fromisoformat(c_text)
        except Exception:
            continue

        try:
            e_text = str(expires)
            if "Z" in e_text:
                e_dt = datetime.fromisoformat(e_text.replace("Z", "+00:00"))
            else:
                e_dt = datetime.fromisoformat(e_text)
        except Exception:
            continue

        if c_dt.tzinfo:
            c_dt = c_dt.replace(tzinfo=None)
        if e_dt.tzinfo:
            e_dt = e_dt.replace(tzinfo=None)

        year = str(e_dt.year)
        per_year.setdefault(year, []).append(
            {
                "serial_number": unit.get("unit_serial_number"),
                "blood_type": unit.get("blood_type"),
                "date_collected": c_dt.date().isoformat(),
                "date_expired": e_dt.date().isoformat(),
            }
        )

    return {"success": True, "per_year": per_year}


def _aggregate_pending_units_for_release() -> Dict[str, Any]:
    """
    Pending units for release from blood_bank_units:
      - is_check is True
      - status indicates Valid/Buffer (still in stock)
    Grouped per year of collected_at.
    """
    units = _fetch_blood_units()
    per_year: Dict[str, List[Dict[str, Any]]] = {}

    for unit in units:
        if not unit.get("is_check"):
            continue

        status = (unit.get("status") or "").strip()
        status_lower = status.lower()
        if status_lower not in ("valid", "buffer", ""):
            continue

        collected = unit.get("collected_at") or unit.get("created_at")
        if not collected:
            continue

        try:
            text = str(collected)
            if "Z" in text:
                dt = datetime.fromisoformat(text.replace("Z", "+00:00"))
            else:
                dt = datetime.fromisoformat(text)
        except Exception:
            continue

        if dt.tzinfo:
            dt = dt.replace(tzinfo=None)

        year = str(dt.year)
        request_id = unit.get("request_id")
        # Per user: if status is Valid/Buffer, do not include request_id value
        out_request_id = "" if status_lower in ("valid", "buffer", "") else request_id

        per_year.setdefault(year, []).append(
            {
                "serial_number": unit.get("unit_serial_number"),
                "blood_type": unit.get("blood_type"),
                "request_id": out_request_id,
                "status": status or "Pending",
            }
        )

    return {"success": True, "per_year": per_year}


def _aggregate_requests_by_status() -> Dict[str, Any]:
    """
    Aggregate hospital requests by status per year from the blood_requests table.

    Returns:
      {
        "success": True,
        "per_year": {
           "2024": { "total": N, "by_status": {"Approved": x, "Declined": y, ...} },
           ...
        }
      }
    """
    db = DatabaseConnection()
    requests: List[Dict[str, Any]] = []
    if db.connect():
        try:
            requests = db.fetch_blood_requests()
        finally:
            db.disconnect()

    per_year: Dict[str, Dict[str, Any]] = {}

    for req in requests:
        requested_on = req.get("requested_on")
        if not requested_on:
            continue
        try:
            text = str(requested_on)
            if "Z" in text:
                dt = datetime.fromisoformat(text.replace("Z", "+00:00"))
            else:
                dt = datetime.fromisoformat(text)
        except Exception:
            continue

        if dt.tzinfo:
            dt = dt.replace(tzinfo=None)

        year = str(dt.year)
        bucket = per_year.setdefault(year, {"total": 0, "by_status": {}})

        status = str(req.get("status") or "Unknown").strip()
        bucket["total"] += 1
        bucket["by_status"][status] = bucket["by_status"].get(status, 0) + 1

    return {
        "success": True,
        "per_year": per_year,
    }


def _aggregate_decline_reasons() -> Dict[str, Any]:
    """
    Aggregate reasons for declined hospital requests from the blood_requests table.

    Uses the decline_reason / decline_reason_enum column and only counts records
    whose status indicates a declined / rejected request.
    """
    db = DatabaseConnection()
    requests: List[Dict[str, Any]] = []
    if db.connect():
        try:
            requests = db.fetch_blood_requests()
        finally:
            db.disconnect()

    per_year_counts: Dict[str, Dict[str, int]] = {}
    per_year_totals: Dict[str, int] = {}

    for req in requests:
        status_text = (req.get("status") or "").lower()
        # Only look at actually declined / rejected requests
        if not any(kw in status_text for kw in ["declin", "reject"]):
            continue

        requested_on = req.get("requested_on")
        if not requested_on:
            continue
        try:
            text = str(requested_on)
            if "Z" in text:
                dt = datetime.fromisoformat(text.replace("Z", "+00:00"))
            else:
                dt = datetime.fromisoformat(text)
        except Exception:
            continue

        if dt.tzinfo:
            dt = dt.replace(tzinfo=None)

        year = str(dt.year)

        reason = (
            req.get("decline_reason")
            or req.get("decline_reason_enum")
            or "Other"
        )
        label = str(reason).strip() or "Other"

        year_counts = per_year_counts.setdefault(year, {})
        year_counts[label] = year_counts.get(label, 0) + 1
        per_year_totals[year] = per_year_totals.get(year, 0) + 1

    per_year_rows: Dict[str, List[Dict[str, Any]]] = {}
    for year, counts in per_year_counts.items():
        total = per_year_totals.get(year, 0) or 1
        rows: List[Dict[str, Any]] = []
        for reason, count in sorted(counts.items(), key=lambda x: -x[1]):
            pct = round((count / total) * 100, 1)
            rows.append(
                {
                    "reason": reason,
                    "count": count,
                    "percentage": pct,
                }
            )
        per_year_rows[year] = rows

    return {
        "success": True,
        "per_year": per_year_rows,
    }


def _generate_demographic_charts() -> Dict[str, str]:
    """
    Generate / refresh interactive HTML charts for the donor & hospital overview.
    Returns a mapping of logical chart keys to filenames (relative within
    assets/reports-model).
    """
    _ensure_charts_dir()

    chart_files: Dict[str, str] = {}

    # Donor demographics
    try:
        age_mod = _load_module("donor_age_distribution", "Donor Age Distribution.py")
        age_path = CHARTS_DIR / "donor_age_distribution.html"
        age_mod.generate_chart_html(str(age_path))
        chart_files["donor_age"] = "charts/donor_age_distribution.html"
    except Exception as exc:  # pragma: no cover - diagnostics only
        print(f"[reports_dashboard_overview] Age chart error: {exc}", file=sys.stderr)

    try:
        loc_mod = _load_module("donor_location_distribution", "Donor Location Distribution.py")
        loc_path = CHARTS_DIR / "donor_location_distribution.html"
        loc_mod.generate_chart_html(str(loc_path))
        chart_files["donor_location"] = "charts/donor_location_distribution.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Location chart error: {exc}", file=sys.stderr)

    try:
        sex_mod = _load_module("donor_sex_distribution", "Donor Sex Distribution.py")
        sex_path = CHARTS_DIR / "donor_sex_distribution.html"
        sex_mod.generate_chart_html(str(sex_path))
        chart_files["donor_sex"] = "charts/donor_sex_distribution.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Sex chart error: {exc}", file=sys.stderr)

    try:
        elig_mod = _load_module("donor_eligibility_status", "Donor Eligibility Status.py")
        elig_path = CHARTS_DIR / "donor_eligibility_status.html"
        # Reuse data-only function; the HTML is not strictly needed yet,
        # but keep a placeholder call pattern for future enhancements.
        if hasattr(elig_mod, "generate_chart_html"):
            elig_mod.generate_chart_html(str(elig_path))
            chart_files["donor_eligibility"] = "charts/donor_eligibility_status.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Eligibility chart error: {exc}", file=sys.stderr)

    try:
        bt_mod = _load_module("donor_blood_type_distribution", "Donor Blood Type Distribution.py")
        bt_path = CHARTS_DIR / "donor_blood_type_distribution.html"
        bt_mod.generate_chart_html(str(bt_path))
        chart_files["donor_blood_type"] = "charts/donor_blood_type_distribution.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Blood type chart error: {exc}", file=sys.stderr)

    try:
        freq_mod = _load_module("donation_frequency", "Donation Frequency.py")
        freq_path = CHARTS_DIR / "donation_frequency.html"
        freq_mod.generate_chart_html(str(freq_path))
        chart_files["donation_frequency"] = "charts/donation_frequency.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Donation frequency chart error: {exc}", file=sys.stderr)

    # Donation activity / channels / outcomes
    try:
        month_mod = _load_module("donations_by_month", "Donations by Month.py")
        month_path = CHARTS_DIR / "donations_by_month.html"
        month_mod.generate_chart_html(str(month_path))
        chart_files["donations_by_month"] = "charts/donations_by_month.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Donations by month chart error: {exc}", file=sys.stderr)

    try:
        mobile_mod = _load_module("mobile_vs_inhouse", "Mobile Drive vs In-House Donations.py")
        mobile_path = CHARTS_DIR / "mobile_vs_inhouse.html"
        mobile_mod.generate_chart_html(str(mobile_path))
        chart_files["mobile_vs_inhouse"] = "charts/mobile_vs_inhouse.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Mobile vs in-house chart error: {exc}", file=sys.stderr)

    try:
        success_mod = _load_module(
            "successful_vs_unsuccessful", "Successful vs Unsuccessful Donations.py"
        )
        success_path = CHARTS_DIR / "successful_vs_unsuccessful.html"
        if hasattr(success_mod, "generate_chart_html"):
            success_mod.generate_chart_html(str(success_path))
            chart_files["successful_vs_unsuccessful"] = "charts/successful_vs_unsuccessful.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Success vs unsuccessful chart error: {exc}", file=sys.stderr)

    # Hospital request patterns
    try:
        req_trend_mod = _load_module(
            "monthly_blood_requests_trend", "Monthly Blood Requests Trend.py"
        )
        req_trend_path = CHARTS_DIR / "monthly_blood_requests_trend.html"
        req_trend_mod.generate_chart_html(str(req_trend_path))
        chart_files["monthly_requests_trend"] = "charts/monthly_blood_requests_trend.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Monthly requests trend chart error: {exc}", file=sys.stderr)

    try:
        req_bt_mod = _load_module("blood_requests_by_type", "Blood Requests by Blood Type.py")
        req_bt_path = CHARTS_DIR / "blood_requests_by_type.html"
        req_bt_mod.generate_chart_html(str(req_bt_path))
        chart_files["requests_by_blood_type"] = "charts/blood_requests_by_type.html"
    except Exception as exc:  # pragma: no cover
        print(f"[reports_dashboard_overview] Requests by blood type chart error: {exc}", file=sys.stderr)

    return chart_files


def generate_overview_payload() -> Dict[str, Any]:
    """
    Entry point for the PHP API.
    Returns a JSON-serialisable dict with KPIs and supporting datasets.
    """
    # --- KPI sources ---
    active_mod = _load_module("total_active_donors", "Total Active Donors.py")
    eligible_mod = _load_module("eligible_donors_today", "Eligible Donors Today.py")
    available_mod = _load_module("total_blood_units_available", "Total Blood Units Available.py")
    nearing_mod = _load_module("blood_units_nearing_expiry", "Blood Units Nearing Expiry.py")
    hosp_today_mod = _load_module("total_hospital_requests_today", "Total Hospital Requests Today.py")

    active_data = _safe_call(active_mod.get_active_donors_data)
    eligible_data = _safe_call(eligible_mod.get_eligible_donors_today)
    available_data = _safe_call(available_mod.get_available_units_data)
    nearing_data = _safe_call(nearing_mod.get_units_nearing_expiry_summary)
    hosp_today_data = _safe_call(hosp_today_mod.get_hospital_requests_today)

    # --- Donor demographics ---
    age_mod = _load_module("donor_age_distribution", "Donor Age Distribution.py")
    loc_mod = _load_module("donor_location_distribution", "Donor Location Distribution.py")
    sex_mod = _load_module("donor_sex_distribution", "Donor Sex Distribution.py")
    elig_status_mod = _load_module("donor_eligibility_status", "Donor Eligibility Status.py")
    bt_mod = _load_module("donor_blood_type_distribution", "Donor Blood Type Distribution.py")
    freq_mod = _load_module("donation_frequency", "Donation Frequency.py")

    age_data = _safe_call(age_mod.get_donor_age_distribution_data)
    loc_data = _safe_call(loc_mod.get_donor_location_distribution_data)
    sex_data = _safe_call(sex_mod.get_donor_sex_distribution_data)
    elig_status_data = _safe_call(elig_status_mod.get_donor_eligibility_status_data)
    bt_data = _safe_call(bt_mod.get_donor_blood_type_distribution_data)
    freq_data = _safe_call(freq_mod.get_donation_frequency_data)

    # --- Donation activity / channels / outcomes ---
    donations_month_mod = _load_module("donations_by_month", "Donations by Month.py")
    mobile_vs_inhouse_mod = _load_module(
        "mobile_vs_inhouse", "Mobile Drive vs In-House Donations.py"
    )
    success_mod = _load_module(
        "successful_vs_unsuccessful", "Successful vs Unsuccessful Donations.py"
    )

    donations_month_data = _safe_call(donations_month_mod.get_donations_by_month_data)
    mobile_vs_inhouse_data = _safe_call(mobile_vs_inhouse_mod.get_mobile_vs_inhouse_data)
    success_data = _safe_call(success_mod.get_donation_success_data)

    # --- Hospital request patterns ---
    monthly_req_trend_mod = _load_module(
        "monthly_blood_requests_trend", "Monthly Blood Requests Trend.py"
    )
    requests_by_type_mod = _load_module("blood_requests_by_type", "Blood Requests by Blood Type.py")

    monthly_req_trend_data = _safe_call(
        monthly_req_trend_mod.get_monthly_requests_trend_data
    ) if hasattr(monthly_req_trend_mod, "get_monthly_requests_trend_data") else {}
    requests_by_type_data = _safe_call(
        requests_by_type_mod.get_requests_by_blood_type_data
    )

    # --- Units / allocation breakdown from blood_bank_units and hospital requests ---
    units_collected_status = _aggregate_units_collected_by_status()
    requests_status_by_year = _aggregate_requests_by_status()
    declined_reasons_data = _aggregate_decline_reasons()
    units_allocated = _aggregate_units_allocated()
    units_expired = _aggregate_units_expired()
    pending_units = _aggregate_pending_units_for_release()

    # --- Charts (HTML) ---
    chart_files = _generate_demographic_charts()

    # --- KPIs for top row ---
    kpis = {
        "total_active_donors": int(active_data.get("total_active", 0)),
        "total_registered_donors": int(active_data.get("total_donors", 0)),
        "eligible_donors_today": int(eligible_data.get("total_eligible", 0)),
        "total_blood_units_available": int(available_data.get("total_available", 0)),
        "units_nearing_expiry": int(nearing_data.get("total_nearing_expiry", 0)),
        "total_hospital_requests_today": int(hosp_today_data.get("total_today", 0)),
    }

    return {
        "success": True,
        "generated_at": datetime.now().isoformat(),
        "kpis": kpis,
        "charts": chart_files,
        "sections": {
            "donor_demographics": {
                "age": age_data,
                "location": loc_data,
                "sex": sex_data,
                "eligibility_status": elig_status_data,
                "blood_type": bt_data,
                "donation_frequency": freq_data,
            },
            "donation_activity": {
                "donations_by_month": donations_month_data,
                "mobile_vs_inhouse": mobile_vs_inhouse_data,
                "success_vs_unsuccessful": success_data,
            },
            "hospital_requests": {
                "requests_today": hosp_today_data,
                "monthly_trend": monthly_req_trend_data,
                "requests_by_blood_type": requests_by_type_data,
                "totals_by_status": requests_status_by_year,
                "declined_reasons": declined_reasons_data,
            },
            "inventory": {
                "available_units": available_data,
                "units_nearing_expiry": nearing_data,
                "units_collected_status_monthly": units_collected_status,
                "units_allocated": units_allocated,
                "units_expired": units_expired,
                "pending_units": pending_units,
            },
            "donor_status": {
                "active_donors": active_data,
                "eligible_today": eligible_data,
            },
        },
    }


def main():
    try:
        payload = generate_overview_payload()
        print(json.dumps(payload, indent=2))
    except Exception as exc:  # pragma: no cover - fatal diagnostics
        error_payload = {"success": False, "error": str(exc)}
        print(json.dumps(error_payload, indent=2))


if __name__ == "__main__":
    main()


