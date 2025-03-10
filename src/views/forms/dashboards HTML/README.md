# Red Cross Inventory System - Temporary Dashboard Testing

## Overview
This project is a temporary setup for testing authentication, role-based access control, and database connectivity using **PostgreSQL Supabase**. The dashboards for **Inventory, Hospital Requests, and Staff** are being developed to evaluate different access levels and redirection functionalities.

## User Access Levels
The system has five defined user levels:

- **Level 0: Admin (Inventory Management)**
  - Full access to manage and update the inventory system.
  - Can add, remove, and modify blood unit records.
  - Can oversee and approve hospital requests.

- **Level 1: Staff Dashboard**
  - Limited access to inventory information.
  - Can assist in updating blood stock records.
  - Can manage donor applications and updates.

- **Level 2: Hospital Request Dashboard**
  - Access to request blood units from inventory.
  - Can submit and track the status of blood requests.
  - Limited visibility into available stock levels.

- **Level 3: Backup Access**
  - Reserved for backup and emergency recovery purposes.
  - Can retrieve critical data for system restoration.
  - No direct access to modify real-time inventory or requests.

- **Level 4: Mobile Application**
  - Access to request blood units from inventory.
  - Can submit and track the status of blood requests.
  - Limited visibility into available stock levels.