
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8" />
    <title>Wartungsmodus</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
    <style>
        body {
            background:
                linear-gradient(to top right, rgba(210, 180, 140, 0.9), rgba(0, 0, 0, 0.9)),
                url('/images/lock_bg.jpg') no-repeat center center;
            background-size: cover;
            height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            padding: 1rem;
            margin: 0;
        }
        .logo {
            margin-bottom: 1.5rem;
        }
        .card {
            max-width: 600px;
            width: 100%;
        }
        ul.countdown {
            display: flex;
            justify-content: center;
            gap: 1rem;
            padding: 0;
            margin: 1rem 0;
            list-style: none;
            flex-wrap: wrap;
        }
        ul.countdown li {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            min-width: 80px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
        }
        ul.countdown li span {
            display: block;
            font-size: 1.5rem;
            font-weight: bold;
            color: #343a40;
        }
        ul.countdown li h3 {
            font-size: 0.9rem;
            margin-top: 0.5rem;
            color: #6c757d;
        }
    </style>
</head>
<body>

    <!-- Logo oben -->
    <div class="logo text-center">
        <img src="/images/logo.png" alt="nexpell Logo" style="height: 150px;" />
    </div>

    <!-- Card darunter -->
    <div class="card shadow-lg p-4">
        <div class="card-body">
            <h1 class="text-danger mb-3"><i class="bi bi-cone-striped"></i> Wartungsmodus</h1>
            <p class="reason text-start"><?= $data_array['reason'] ?? 'Wartungsmodus aktiv.' ?></p>

            <?php
            $startTime = $data_array['time'] ?? time();
            ?>

            <p class="text-muted small">Seit: <?= date("d.m.Y - H:i", $startTime) ?></p>

            <ul class="countdown">
                <li><span id="days">0</span><h3>Tage</h3></li>
                <li><span id="hours">00</span><h3>Stunden</h3></li>
                <li><span id="minutes">00</span><h3>Minuten</h3></li>
                <li><span id="seconds">00</span><h3>Sekunden</h3></li>
            </ul>

            <a href="/admin/login.php" class="btn btn-outline-success btn-sm mt-3">Zum Admin-Login</a>
        </div>
    </div>

    <script>
        // Startzeit vom Server (Unix-Timestamp in Millisekunden)
        const startTime = <?= (int)$startTime ?> * 1000;

        function updateCountdown() {
            const now = Date.now();
            let elapsed = Math.floor((now - startTime) / 1000); // Sekunden

            if (elapsed < 0) elapsed = 0; // falls Startzeit in Zukunft

            const days = Math.floor(elapsed / 86400);
            const hours = Math.floor((elapsed % 86400) / 3600);
            const minutes = Math.floor((elapsed % 3600) / 60);
            const seconds = elapsed % 60;

            document.getElementById('days').textContent = days;
            document.getElementById('hours').textContent = hours.toString().padStart(2, '0');
            document.getElementById('minutes').textContent = minutes.toString().padStart(2, '0');
            document.getElementById('seconds').textContent = seconds.toString().padStart(2, '0');
        }

        updateCountdown();
        setInterval(updateCountdown, 1000);
    </script>

</body>
</html>
