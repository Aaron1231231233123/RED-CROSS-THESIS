<?php
require 'db_conn.php';

/**
 * Function to generate a valid UUID v4.
 */
function generateUUID() {
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Function to register a user in Supabase.
 */
function registerUser($user_id, $surname, $first_name, $middle_name, $suffix, $email, $phone_number, $telephone_number, $date_of_birth, $gender, $permanent_address, $office_address, $password_hash) {
    $data = [
        'user_id' => $user_id, 
        'surname' => $surname,
        'first_name' => $first_name,
        'middle_name' => $middle_name,
        'suffix' => $suffix,
        'email' => $email,
        'phone_number' => $phone_number,
        'telephone_number' => $telephone_number,
        'date_of_birth' => $date_of_birth,
        'gender' => $gender,
        'permanent_address' => $permanent_address,
        'office_address' => $office_address,
        'password_hash' => $password_hash
    ];

    return supabaseRequest("users_staff", "POST", $data); // Insert user data into Supabase
}

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Generate a proper UUID
    $user_id = generateUUID();

    // Get form data safely
    $surname = $_POST['surname'] ?? '';
    $first_name = $_POST['first_name'] ?? '';
    $middle_name = $_POST['middle_name'] ?? '';
    $suffix = $_POST['suffix'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $telephone_number = $_POST['telephone_number'] ?? '';
    $date_of_birth = $_POST['date_of_birth'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $permanent_address = $_POST['permanent_address'] ?? '';
    $office_address = $_POST['office_address'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validate required fields
    if (empty($surname) || empty($first_name) || empty($email) || empty($password) || empty($confirm_password)) { 
        die("❌ Error: Required fields are missing.");
    }

    // Validate passwords match
    if ($password !== $confirm_password) {
        die("❌ Error: Passwords do not match.");
    }

    // Hash the password before storing it
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Register the user
    $result = registerUser(
        $user_id, // Pass the generated user ID
        $surname, $first_name, $middle_name, $suffix, 
        $email, $phone_number, $telephone_number, $date_of_birth, 
        $gender, $permanent_address, $office_address, $hashed_password
    );

    if ($result) {
        echo "✅ Registration successful!";
    } else {
        echo "❌ Registration failed.";
    }
}
?>









<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Volunteer Registration</title>
    <style>
        * {
            box-sizing: border-box;
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Arial', sans-serif;
            background-color: #f9f9f9;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
            overflow-y: auto;
        }

        /* Form Container */
        .form-container {
            background: white;
            padding: 20px;
            width: 100%;
            max-width: 750px;
            border-radius: 10px;
            box-shadow: 0 0 15px rgba(0, 0, 0, 0.1);
            position: relative;
            text-align: center;
            overflow: hidden;
        }

        /* Close Button */
        .close-btn {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 16px;
            cursor: pointer;
        }

        /* Title */
        h2 {
            color: #d32f2f;
            font-size: 36px;
            margin-bottom: 8px;
            text-align: center;
            font-weight: bold;
        }

        /* Label & Input Styling */
        .form-group {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 15px;
            flex-wrap: wrap;
        }

        .input-box {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        label {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 4px;
            text-align: left;
        }

        .form-container hr {
            border: 0;
            height: 2px;
            background-color: #d32f2f;
            margin: 8px auto 15px;
            width: 60px;
        }

        .reg-input-type input, select, textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 16px;
        }

        .reg-input-box {
            margin-bottom: 12px;
        }
        /* Hidden Auto Address Suggestion */
        .suggestions-container {
                position: relative;
                width: 100%;
            }
            .suggestions-box {
                list-style: none;
                background: white;
                border: 1px solid #ccc;
                border-radius: 5px;
                max-height: 200px;
                overflow-y: auto;
                position: absolute;
                width: 100%;
                padding: 0;
                margin-top: -12px;
                display: none;
                z-index: 1;
            }
            .suggestions-box li {
                padding: 10px;
                cursor: pointer;
                border-bottom: 1px solid #eee;
            }
            .suggestions-box li:hover {
                background: #f0f0f0;
            }

            /* Responsive Fix */
            @media (max-width: 600px) {
                .suggestions-box {
                    max-height: 150px;
                    font-size: 14px;
                }
            }

        /* Ensure Address Fields Have Proper Spacing */
        .address-group input {
            margin-bottom: 8px;
        }

        /* Extra spacing for ALL fields below "Date of Birth" */
        .date-group, .gender-group, .address-group, .terms-container {
            margin-bottom: 15px;
        }

        /* Make Password fields take full width */
        .password-group {
            display: flex;
            flex-direction: column;
            gap: 8px;
            margin-top: 15px;
        }

        .password-group input {
            width: 100%;
        }

        /* Terms & Conditions Section */
        .terms-container {
            font-size: 10px;
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            background-color: #d90429;
            color: white;
            padding: 10px;
            border: none;
            border-radius: 4px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 12px;
        }

        .submit-btn:hover {
            background-color: #b60321;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .form-container {
                width: 95%;
                padding: 15px;
            }

            .form-group {
                flex-direction: column;
                gap: 12px;
            }
        }

        @media (max-width: 480px) {
            h2 {
                font-size: 28px;
            }

            label {
                font-size: 14px;
            }

            .submit-btn {
                font-size: 12px;
                padding: 8px;
            }
        }

        .custom-terms {
            display: flex;
            align-items: center;
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
        }

        .custom-terms input {
            display: none;
        }

        .checker {
            display: flex;
            align-items: center;
            cursor: pointer;
            position: relative;
        }

        /* Custom checkbox design */
        .checker::before {
            content: "";
            width: 14px;
            height: 14px;
            border: 2px solid #333;
            border-radius: 3px;
            display: inline-block;
            margin-right: 6px;
            background-color: #fff;
            transition: background 0.2s, border-color 0.2s;
        }

        /* Checked state */
        .custom-terms input:checked + .checker::before {
            background-color: #ff6b6b;
            border-color: #ff6b6b;
            content: "✔";
            color: white;
            font-size: 10px;
            text-align: center;
            line-height: 12px;
            font-weight: bold;
        }

        /* Styling for Terms and Privacy Policies */
        .terms, .privacy {
            color: #ff6b6b;
            font-weight: bold;
            margin-right: 5px;
            margin-left: 5px;
        }
        input::placeholder, 
        option{
            font-style: italic;
        }
        select {
            font-style: italic;
            color: gray;
        }

        /* Make selected option (not placeholder) appear normal */
        select option {
            font-style: normal;
            color: black;
        }

        /* Style the first option (placeholder) */
        select option:first-child{
            font-style: italic;
            color: gray;
        }
    </style>
</head>
<body>

<div class="form-container" id="registration-form">
    <button class="close-btn" onclick="closeForm()">✖</button>
    <h2>Sign up</h2>
    <hr>
    <br>
    <form action="registration_form.php" method="POST">
        <div class="form-group reg-input-type">
            <div class="input-box">
                <label>Surname</label>
                <input type="text" name="surname" placeholder="Surname" required>
            </div>
            <div class="input-box">
                <label>First Name</label>
                <input type="text" name="first_name" placeholder="First Name" required>
            </div>
            <div class="input-box">
                <label>Middle Name</label>
                <input type="text" name="middle_name" placeholder="Middle Name">
            </div>
            <div class="input-box">
                <label>Suffix</label>
                <select name="suffix">
                    <option value="">None</option>
                    <option value="Jr.">Jr.</option>
                    <option value="Sr.">Sr.</option>
                    <option value="III">III</option>
                </select>
            </div>
        </div>

        <div class="form-group reg-input-type">
            <div class="input-box">
                <label>Email</label>
                <input type="email" name="email" placeholder="@-" required>
            </div>
            <div class="input-box">
                <label>Phone Number</label>
                <input type="tel" name="phone_number" pattern="[0-9]{11}" maxlength="11" placeholder="0-9" required>
            </div>
            <div class="input-box">
                <label>Telephone Number</label>
                <input type="tel" name="telephone_number" pattern="[0-9]{11}" maxlength="11" placeholder="0-9">
            </div>
        </div>

        <div class="form-group reg-input-type">
            <div class="input-box">
                <label for="date_of_birth">Date of Birth</label>
                <input type="date" name="date_of_birth" required>
            </div>
            <div class="input-box">
                <label>Gender</label>
                <select name="gender">
                <option value="">None</option>
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                </select>
            </div>
        </div>

        <div class="input-box reg-input-type">
            <label class="reg-input-box">Permanent Address</label>
            <input class="reg-input-box address-input" type="text" name="permanent_address" id="permanent-address" 
                placeholder="Street, Barangay, Town/Municipal, Province/City, Zip Code" 
                oninput="fetchAddressSuggestions(this, 'permanent')" required>
            <div class="suggestions-container">
                <ul class="suggestions-box" id="permanent-suggestions"></ul>
            </div>
        </div>

        <div class="input-box reg-input-type">
            <label class="reg-input-box">Office Address</label>
            <input class="reg-input-box address-input" type="text" name="office_address" id="office-address" 
                placeholder="Street, Barangay, Town/Municipal, Province/City, Zip Code" 
                oninput="fetchAddressSuggestions(this, 'office')" required>
            <div class="suggestions-container">
                <ul class="suggestions-box" id="office-suggestions"></ul>
            </div>
        </div>

        <div class="input-box reg-input-type">
            <label class="reg-input-box">Password</label>
            <input class="reg-input-box" type="password" name="password" placeholder="Please write your password" required>
        </div>
        <div class="input-box reg-input-type">
            <label class="reg-input-box">Confirm Password</label>
            <input class="reg-input-box" type="password" name="confirm_password" placeholder="Please confirm your password" required>
        </div>

        <div class="custom-terms">
            <input class="reg-input-box" type="checkbox" id="termsCheckbox" name="agree_terms" required>
            <label class="checker" for="termsCheckbox">I agree to all the <span class="terms">Terms </span>  
                and <span class="privacy">Privacy Policies </span>
            </label>
        </div>

        <button type="submit" class="submit-btn">Submit</button>
    </form>
</div>

    <script>
        const API_KEY = "pk.3e26d125ab5281d989f03e473ad78b14"; 

            /**
             * Fetch address suggestions based on user input, but restrict results to Iloilo, Philippines.
             * 
             * @param {HTMLElement} input - The input field where the user types the address.
             * @param {string} type - The type of address being entered ("permanent" or "office").
             */
            async function fetchAddressSuggestions(input, type) {
                let query = input.value;

                // ✅ Hide suggestions if input is too short
                if (query.length < 3) {
                    document.getElementById(`${type}-suggestions`).style.display = "none";
                    return;
                }

                // ✅ Fetch suggestions from LocationIQ, limiting results to Iloilo, Philippines
                let response = await fetch(`https://api.locationiq.com/v1/autocomplete.php?key=${API_KEY}&q=${query}, Iloilo, Philippines&countrycodes=PH&format=json`);
                let data = await response.json();

                let suggestionsBox = document.getElementById(`${type}-suggestions`);
                suggestionsBox.innerHTML = ""; // ✅ Clear old suggestions
                suggestionsBox.style.display = "block"; // ✅ Show suggestions box

                // ✅ Filter results to ensure they contain "Iloilo"
                let iloiloResults = data.filter(place => place.display_name.includes("Iloilo"));

                // ✅ Show "No results" if no Iloilo-based addresses are found
                if (iloiloResults.length === 0) {
                    let li = document.createElement("li");
                    li.innerText = "No results found in Iloilo";
                    suggestionsBox.appendChild(li);
                    return;
                }

                // ✅ Display filtered suggestions in dropdown
                iloiloResults.forEach(place => {
                    let li = document.createElement("li");
                    li.innerText = place.display_name;

                    // ✅ When clicked, set the input field value and hide the suggestions
                    li.onclick = function () {
                        input.value = place.display_name;
                        suggestionsBox.style.display = "none";
                    };

                    suggestionsBox.appendChild(li);
                });
            }


    </script>

</body>
</html>
