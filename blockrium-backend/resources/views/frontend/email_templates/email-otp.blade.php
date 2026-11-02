<!DOCTYPE html>
<html lang="en">

<head>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(to right, #ED207B, #BB25AA, #AD27B6, #F68069);
            margin: 0;
            padding: 0;
        }

        .container {
            padding-top: 2rem;
        }

        .card {
            border: 1px solid linear-gradient(to right, #ED207B, #BB25AA, #AD27B6, #F68069);
            border-radius: 5px;
            box-shadow: 0 2px 5px rgba(6, 1, 1, 0.1);
            background-color: white;
        }

        .card-title {
            text-align: center;
            font-size: 24px;
            margin-bottom: 1rem;
            color: #333;
            width: 100%;
        }

        .card-body {
            flex: 1 1 auto;
            padding: var(--bs-card-spacer-y) var(--bs-card-spacer-x);
            color: var(--bs-card-color);
            background-color: linear-gradient(to right, #ED207B, #BB25AA, #AD27B6, #F68069);
        }

        .card-text {
            margin-bottom: 1rem;
            font-size: 16px;
            line-height: 1.5;
            color: #020101;
        }

        .text-danger {
            color: #dc3545;
        }

        strong {
            font-size: 20px;
            color: #007bff;
        }

        .text-center {
            margin-top: 2rem;
        }

        .box {
            background-color: #f5f5f5;
            padding: 20px;
            border-radius: 10px;
        }

        @media (max-width: 768px) {
            .card {
                margin-top: 2rem;
            }
        }

        /* Header Styles */
        header {
            background-color: white; /* White background for the header */
            padding: 10px 0;
            text-align: center;
        }

        header img {
            width: 150px; /* Adjust the logo size */
        }

        /* Footer Styles */
        footer {
            background-color: white;
           
            padding: 20px 0;
            text-align: center;
            position: fixed;
            bottom: 0;
            width: 100%;
        }

        footer .social-icons i {
            margin: 0 10px;
            font-size: 24px;
            color: white;
            text-decoration: none;
        }

        footer .social-icons i:hover {
            color: #007bff;
        }
    </style>
</head>

<body>
    <!-- Header with Blockrium Logo -->
    <header>
        <img src="https://blockrium.com/public/frontend_new/images/main%20logo_full%201.png" alt="Blockrium Logo">
    </header>

    <div class="box">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-body">
                            <p class="card-text">
                                <span id="greet"><b>Dear,</b> </span>{{$data['username']}} <br><br>
                                You're just one step away from updating your password for your Blockrium account. Please use the one-time verification code (OTP) below to complete the process:
                            </p>
                            <p class="card-text">Verification Code (OTP): <strong>{{$data['otp']}}</strong></p>
                            <p class="card-text">Validity: 10 minutes</p>
                            <p class="card-text text-danger">
                               Please keep this code confidential and do not share it with anyone. This ensures the security of your Blockrium account.
                            </p>
                            
                            <p class="card-text text-danger">
                               Thank you for using Blockrium!
                            </p>
                        </div>
                        <div class="text-center mt-4">
                            <p>Best regards,</p>
                            <p><b>Blockrium Support</b></p>
                        </div>
                    </div>     
                </div>
            </div>
        </div>
    </div>

    <!-- Footer with Social Media Icons -->
    <footer>
        <div class="container">
            <div class="social-icons">
                <a href="https://facebook.com" target="_blank"><i class="fab fa-facebook"></i></a>
                <a href="https://twitter.com" target="_blank"><i class="fab fa-twitter"></i></a>
                <a href="https://linkedin.com" target="_blank"><i class="fab fa-linkedin"></i></a>
                <a href="https://instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
            </div>
        </div>
    </footer>
</body>

</html>
