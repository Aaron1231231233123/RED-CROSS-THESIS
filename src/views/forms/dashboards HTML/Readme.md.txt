Red Cross Inventory System - Temporary Dashboard Testing
Overview
This project is a temporary setup for testing authentication, role-based access control, and database connectivity using PostgreSQL Supabase. The dashboards for Inventory, Hospital Requests, and Staff are being developed to evaluate different access levels and redirection functionalities.

User Access Levels
The system has five defined user levels:

--Level 0: Admin (Inventory Management)

-Full access to manage and update the inventory system.
-Can add, remove, and modify blood unit records.
-Can oversee and approve hospital requests.

--Level 1: Staff Dashboard

-Limited access to inventory information.
-Can assist in updating blood stock records.
-Can manage donor applications and updates.

--Level 2: Hospital Request Dashboard

-Access to request blood units from inventory.
-Can submit and track the status of blood requests.
-Limited visibility into available stock levels.

--Level 3: Backup Access

-Reserved for backup and emergency recovery purposes.
-Can retrieve critical data for system restoration.
-No direct access to modify real-time inventory or requests.

--Level 4: Mobile Application (Future Integration)

-Allows blood donors to track their donations and request status.
-Push notifications for donation schedules and emergency needs.
-Secure access to personal donation history.
-Purpose of This Setup

**This setup is purely for testing and is subject to changes as the development progresses. The focus is on:

*Verifying authentication and redirection per access level.
*Understanding how Supabase (PostgreSQL) integrates with the system.
*Testing the connection between the front-end and back-end functionalities.
*Ensuring data security and controlled access for different users.

***Testing Scenarios***
**To ensure proper functionality, the following test cases will be conducted:

*Login Testing: Verifying redirection and authentication for each access level.
*Database Connectivity: Checking if blood stock data syncs correctly with Supabase.
*Role-Based Permissions: Ensuring users can only access allowed features.
*Error Handling: Testing incorrect login attempts and data validation.

***Future Improvements***
*Implement real-time notifications for urgent blood requests.
*Integrate a mobile application for blood donors.
*Implement machine learning-based demand forecasting using time-series analysis.

ps./This document is part of the ongoing development of the Red Cross Inventory System. All features and access levels are subject to modification./

