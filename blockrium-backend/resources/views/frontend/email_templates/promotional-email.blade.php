<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{$data['mailsubject']}}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        table {
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }
        .header {
            background-color: #007bff;
            color: white;
            padding: 20px;
            text-align: center;
        }
        .main-content {
            padding: 20px;
            text-align: center;
        }
        .cta-button {
            background-color: #28a745;
            color: white;
            padding: 12px 20px;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
            margin-top: 20px;
        }
        .features {
            display: flex;
            justify-content: space-between;
            margin-top: 20px;
        }
        .feature {
            text-align: center;
            width: 30%;
        }
        .feature img {
            width: 50px;
            height: 50px;
        }
        .footer {
            background-color: #222222;
            color: white;
            text-align: center;
            padding: 20px;
        }
        .footer a {
            color: #ff5722;
            text-decoration: none;
        }
    </style>
</head>
<body>

    <table>
        <tr>
            <td class="header">
                <h1>{{$data['mailsubject']}}</h1>
                <p>Access all your favorite features in one place.</p>
            </td>
        </tr>
        <tr>
            <td class="main-content">
                <img src="https://blockrium.com/public/frontend_new/images/main%20logo_full%201.png" alt="Blockrium Network" style="width: 100%; max-width: 400px; height: auto;">
                <h2>Dear, {{$data['username']}}</h2>
                <p>{!! $data['mailbody'] !!}</p>
                <a href="https://play.google.com/store/apps/details?id=com.blockrium" class="cta-button">Download Now</a>
                
                
            </td>
        </tr>
        <tr>
            <td class="footer">
                <p>Follow us on social media for updates:</p>
                <p>
                    <a href="https://www.facebook.com/profile.php?id=61561116466127&mibextid=ZbWKwL" target="_blank">Facebook</a> |
                    <a href="https://t.me/blockrium" target="_blank">Telegram</a> |
                    <a href="https://x.com/Blockrium" target="_blank">Twitter</a> |
                    <a href="https://whatsapp.com/channel/0029VaZpv8UF1YlJBjc6J43R" target="_blank">Whatsapp</a> |
                    <a href="https://www.instagram.com/blockrium?igsh=Mmdkd2VxbGhvN294" target="_blank">Instagram</a> |
                    <a href="https://www.threads.net/@blockrium?invite=0" target="_blank">Threads</a>
                </p>
                <p><a href="{{$data['unsubscribe_url'] ?? '#'}}" style="color: #ff5722;">Manage batch email preferences</a></p>
            </td>
        </tr>
    </table>

</body>
</html>
