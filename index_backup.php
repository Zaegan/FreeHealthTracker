<!DOCTYPE html>
<html>
<head>
    <title>Welcome</title>
    <script>
        function updateTime() {
            const now = new Date();
            document.getElementById("clock").innerText = now.toLocaleString();
        }
        setInterval(updateTime, 1000);
        window.onload = updateTime;
    </script>
</head>
<body>
    <h1>Welcome to atlantiscertificateadmin.vip!</h1>
    <p>This site is currently under construction.</p>
    <p>The current time is: <span id="clock"><?php echo date("Y-m-d H:i:s"); ?></span></p>
</body>
</html>
