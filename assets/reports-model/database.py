"""
Database operations for forecast reports
"""

import requests
from typing import Dict, List
from config import SUPABASE_URL, SUPABASE_API_KEY


class DatabaseConnection:
    """Handles database connections and queries"""
    
    def __init__(self):
        self.supabase_url = SUPABASE_URL
        self.api_key = SUPABASE_API_KEY
        self.connection_active = False
    
    def connect(self) -> bool:
        """Connect to Supabase database"""
        try:
            headers = {
                'apikey': self.api_key,
                'Authorization': f'Bearer {self.api_key}',
                'Content-Type': 'application/json'
            }
            test_url = f"{self.supabase_url}/rest/v1/"
            response = requests.get(test_url, headers=headers, timeout=10)
            if response.status_code == 200 or response.status_code == 400:
                self.connection_active = True
                return True
            return False
        except Exception as e:
            import sys
            print(f"Database connection error: {e}", file=sys.stderr)
            self.connection_active = False
            return False
    
    def disconnect(self):
        """Disconnect from database"""
        self.connection_active = False
    
    def supabase_request(self, endpoint: str, limit: int = 5000) -> List[Dict]:
        """
        Make Supabase requests with pagination support (optimized)
        Uses larger page size for fewer round trips
        FIXED: Added cache-busting headers (not URL params) to ensure real-time data
        """
        all_data = []
        offset = 0
        
        while True:
            url = f"{self.supabase_url}/rest/v1/{endpoint}"
            # FIXED: Add pagination parameters correctly (Supabase doesn't like arbitrary URL params)
            if '?' in endpoint:
                url += f"&limit={limit}&offset={offset}"
            else:
                url += f"?limit={limit}&offset={offset}"
            
            headers = {
                'apikey': self.api_key,
                'Authorization': f'Bearer {self.api_key}',
                'Content-Type': 'application/json',
                'Accept-Encoding': 'gzip, deflate',  # Request compression
                'Cache-Control': 'no-cache, no-store, must-revalidate',  # Prevent caching via headers
                'Pragma': 'no-cache',
                'Expires': '0',
                'Prefer': 'return=representation'  # Supabase header to ensure fresh data
            }
            
            try:
                response = requests.get(url, headers=headers, timeout=60)
                if response.status_code != 200:
                    import sys
                    error_msg = response.text[:1000] if hasattr(response, 'text') else str(response.content[:1000])
                    print(f"ERROR: Supabase request failed with code: {response.status_code}", file=sys.stderr)
                    print(f"  - Endpoint: {endpoint}", file=sys.stderr)
                    print(f"  - URL (truncated): {url[:200]}...", file=sys.stderr)
                    print(f"  - Error response: {error_msg}", file=sys.stderr)
                    # Try to parse error message
                    try:
                        error_json = response.json()
                        if isinstance(error_json, dict) and 'message' in error_json:
                            print(f"  - Supabase error message: {error_json['message']}", file=sys.stderr)
                    except:
                        pass
                    raise Exception(f'Supabase request failed with code: {response.status_code}: {error_msg[:200]}')
                
                data = response.json()
                if not isinstance(data, list):
                    import sys
                    print(f"WARNING: Supabase returned non-list data: {type(data)}", file=sys.stderr)
                    print(f"  - Data: {str(data)[:200]}", file=sys.stderr)
                    data = []
                
                all_data.extend(data)
                
                # DEBUG: Log progress
                if offset == 0:
                    import sys
                    print(f"DEBUG: Fetched {len(data)} records from {endpoint} (first batch)", file=sys.stderr)
                
                # Break if we got fewer records than requested (end of data)
                if len(data) < limit:
                    break
                    
                offset += limit
                    
            except Exception as e:
                import sys
                print(f"Error in supabase_request: {e}", file=sys.stderr)
                break
        
        return all_data
    
    def fetch_blood_units(self) -> List[Dict]:
        """
        Fetch ALL blood bank units data from blood_bank_units table
        Includes collected_at for shelf life calculations (45 days from collection)
        """
        endpoint = "blood_bank_units?select=unit_id,blood_type,collected_at,created_at,status,handed_over_at,expires_at&order=collected_at.asc"
        return self.supabase_request(endpoint)
    
    def fetch_blood_requests(self) -> List[Dict]:
        """
        Fetch ALL blood requests data (hospital requests = demand)
        """
        endpoint = "blood_requests?select=patient_blood_type,rh_factor,units_requested,requested_on,status,handed_over_date&order=requested_on.asc"
        return self.supabase_request(endpoint)

