<?php
session_start();
include 'db/db_connect.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'];
    $pass = $_POST['password'];

    $stmt = $conn->prepare("SELECT user_id, username, password_hash, role FROM users WHERE username = ?");
    $stmt->bind_param("s", $user);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $row = $result->fetch_assoc();
        
        if (password_verify($pass, $row['password_hash'])) {
            $_SESSION['user_id'] = $row['user_id'];
            $_SESSION['username'] = $row['username'];
            $_SESSION['role'] = $row['role'];
            
            header("Location: index.php");
            exit;
        } else {
            $error = "Incorrect password.";
        }
    } else {
        $error = "User not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Mall Monitor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { 
            background: linear-gradient(135deg, #0f0f23 0%, #1a1a2e 50%, #16213e 100%); 
            font-family: 'Inter', sans-serif; 
            display: flex; 
            align-items: center; 
            justify-content: center; 
            height: 100vh; 
            margin: 0; 
            color: #e2e8f0; 
        }
        .login-card { 
            background: rgba(255, 255, 255, 0.05); 
            backdrop-filter: blur(20px); 
            padding: 50px; 
            border-radius: 20px; 
            border: 1px solid rgba(255, 255, 255, 0.1); 
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3); 
            width: 100%; 
            max-width: 420px; 
            text-align: center; 
            position: relative; 
        }
        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(168, 85, 247, 0.1) 100%);
            border-radius: 20px;
            z-index: -1;
        }
        h1 { 
            margin: 0 0 15px; 
            color: #f1f5f9; 
            font-size: 28px; 
            font-weight: 700; 
            text-shadow: 0 0 20px rgba(99, 102, 241, 0.5); 
        }
        p { 
            color: #cbd5e1; 
            margin-bottom: 35px; 
            font-size: 15px; 
        }
        input { 
            width: 100%; 
            padding: 15px; 
            margin-bottom: 20px; 
            border: 1px solid rgba(255, 255, 255, 0.2); 
            border-radius: 12px; 
            box-sizing: border-box; 
            font-size: 15px; 
            background: rgba(255, 255, 255, 0.05); 
            color: #e2e8f0; 
            transition: all 0.3s ease; 
        }
        input::placeholder { color: #94a3b8; }
        input:focus { 
            border-color: #6366f1; 
            outline: none; 
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.2); 
            background: rgba(255, 255, 255, 0.1); 
        }
        button { 
            width: 100%; 
            padding: 15px; 
            background: linear-gradient(135deg, #6366f1 0%, #8b5cf6 100%); 
            color: white; 
            border: none; 
            border-radius: 12px; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.3s ease; 
            box-shadow: 0 4px 15px rgba(99, 102, 241, 0.3); 
        }
        button:hover { 
            background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); 
            box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4); 
            transform: translateY(-2px); 
        }
        .error { 
            color: #f87171; 
            background: rgba(239, 68, 68, 0.1); 
            border: 1px solid rgba(239, 68, 68, 0.2); 
            padding: 12px; 
            border-radius: 8px; 
            font-size: 14px; 
            margin-bottom: 25px; 
            display: block; 
        }
    </style>
</head>
<body>
    <div class="login-card">
        <h1>Mall Monitor</h1>
        <p>Enter your credentials to access the command center.</p>
        
        <?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; ?>
        
        <form method="POST">
            <input type="text" name="username" placeholder="Username" required autofocus>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Sign In</button>
        </form>
    </div>
    <div style="position: fixed; bottom: 10px; width: 100%; text-align: center; color: #64748b; font-size: 11px; font-family: 'Inter', sans-serif;">
    &copy; 2026 Mall Monitor System | Developed by <span style="font-weight:700; color:#3b82f6;">TaruProd</span>
</div>
</body>
</html>