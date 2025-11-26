"""
Python equivalent of `7 - Statistics.R`.
Prints high-level stats from live Supabase data.
"""

from __future__ import annotations

import pandas as pd

from database import DatabaseConnection


def _load_data() -> tuple[pd.DataFrame, pd.DataFrame]:
    db = DatabaseConnection()
    if not db.connect():
        raise RuntimeError("Unable to connect to Supabase")

    try:
        donations = pd.DataFrame(db.fetch_blood_units())
        requests = pd.DataFrame(db.fetch_blood_requests())
    finally:
        db.disconnect()

    return donations, requests


def _safe_sum(series: pd.Series) -> float:
    return float(pd.to_numeric(series, errors="coerce").fillna(0).sum())


def _print_section(title: str):
    print(f"\n{title}")
    print("=" * len(title))


def print_summary_statistics():
    donations, requests = _load_data()

    total_donations = len(donations)
    total_requests = len(requests)
    total_units_requested = _safe_sum(requests.get("units_requested", pd.Series(dtype=float)))

    if "is_asap" in requests.columns:
        asap_count = requests["is_asap"].fillna(False).astype(bool).sum()
    else:
        asap_count = 0

    asap_pct = (asap_count / total_requests * 100) if total_requests else 0.0

    print("\n📈 OVERALL SUMMARY")
    print("===================")
    print(f"Total Donations: {total_donations}")
    print(f"Total Requests: {total_requests}")
    print(f"Total Units Requested: {int(total_units_requested)}")
    print(f"ASAP Requests: {asap_count} ({asap_pct:.1f}%)\n")

    _print_section("Request Status Distribution")
    if "status" in requests.columns:
        print(requests["status"].value_counts(dropna=False).to_string())
    else:
        print("(status column not available)")

    _print_section("Most Common Diagnoses")
    if "patient_diagnosis" in requests.columns:
        print(
            requests["patient_diagnosis"]
            .fillna("Unknown")
            .value_counts()
            .head(5)
            .to_string()
        )
    else:
        print("(patient_diagnosis column not available)")

    _print_section("Blood Component Distribution")
    if "blood_component" in requests.columns:
        print(requests["blood_component"].fillna("Unknown").value_counts().to_string())
    else:
        print("(blood_component column not available)")


if __name__ == "__main__":
    print_summary_statistics()



