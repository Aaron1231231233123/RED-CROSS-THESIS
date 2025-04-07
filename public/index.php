<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Red Cross Blood Bank - Inventory Management System</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            text-align: center;
            margin: 0;
            padding: 0;
            background-color: #f8f8f8;
        }

        .title-container {
            display: flex;
            align-items: center;
            justify-content: center;
            margin-top: 20px;
        }

        .cross {
            width: 80px;  
            height: 80px;  
            position: relative;
        }

        .cross::before,
        .cross::after {
            content: "";
            position: absolute;
            background-color: #9c1818; /* Red cross color */
        }

        .cross::before {
            width: 80px;   
            height: 20px;  
            top: 30px;     
            left: 0;
        }

        .cross::after {
            width: 20px;   
            height: 80px;  
            top: 0;
            left: 30px;    
        }

        .header-title {
            font-size: 48px;  
            font-weight: bold;
            color: #9c1818;  
            margin-left: 30px;  
            text-align: left;
        }

        .container {
            display: flex;
            justify-content: center;
            gap: 60px;
            flex-wrap: wrap;
        }

        .card {
            background: white;
            padding: 50px 40px;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.301);
            text-align: center;
            width: 240px;
            transition: transform 0.2s;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            margin-top: 5%;
            /* Added cursor pointer to make it clear cards are clickable */
            cursor: pointer;
        }

        .card:hover {
            transform: scale(1.05);
        }

        .icon {
            margin-top: 20%;
            width: 70px;
            height: 70px;
            opacity: 0.8;
        }

        .card p {
            font-size: 1.6em;
            font-weight: bold;
            color: #7a1010;
        }

        /* Added styling for links to maintain design consistency */
        .card-link {
            text-decoration: none;
            color: inherit;
        }

        @media (max-width: 768px) {
            .container {
                flex-direction: column;
                align-items: center;
                gap: 20px;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="title-container">
            <div class="cross"></div>
            <h1 class="header-title">
                Red Cross Blood Bank <br>
                Inventory Management System
            </h1>
        </div>
    </header>
    <main>
        <div class="container">
            <!-- Added links around each card that redirect to login.php -->
            <!-- Added role parameter to differentiate user types in the login form -->
            <a href="hospital-request.php" class="card-link">
                <div class="card">
                    <div class="icon-wrapper">
                        <img src="assets/img/hospital-icon.png" alt="Hospitals" class="icon">
                    </div>
                    <p>Hospitals</p>
                </div>
            </a>

            <a href="login.php" class="card-link">
                <div class="card">
                    <div class="icon-wrapper">
                        <img src="assets/img/staff-icon.png" alt="Staff" class="icon">
                    </div>
                    <p>Staff</p>
                </div>
            </a>

            <a href="login.php" class="card-link">
                <div class="card">
                    <div class="icon-wrapper">
                        <img src="assets/img/admin-icon.png" alt="Admin" class="icon">
                    </div>
                    <p>Admin</p>
                </div>
            </a>
        </div>
    </main>
</body>
</html>