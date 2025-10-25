<head>
  <title>ELECT</title>
  <link rel="stylesheet" href="css/header.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Saira:wght@600&display=swap" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;700&display=swap" />
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Unbounded:wght@600&display=swap" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>

<body>
  <div class="header">
    <div class="navbar">
      <div class="nav-content">
        <div class="nav-left">
          <div class="title">
            <a href="user/logout.php" title="Logout">
              <img class="logo-nav dark" src="assets/LOGO.png" alt="ELECT LOGO">
            </a>
            <h1 style="font-size: 30px; padding: 5px;">EVERGREEN</h1>
          </div>

        </div>

        <div class="nav-right">
          <div class="date">
            <div class="date1" id="currentDate">Date</div>
          </div>

          <!-- <div class="time">
            <div class="time1" id="currentTime">Time</div>
          </div> -->
        </div>

        <script>
          function updateDateTime() {
            const now = new Date();

            const options = {
              year: 'numeric',
              month: 'long',
              day: 'numeric'
            };
            const date = now.toLocaleDateString(undefined, options);
            const time = now.toLocaleTimeString();

        
            document.getElementById('currentDate').textContent = date;
            document.getElementById('currentTime').textContent = time;
          }

          setInterval(updateDateTime, 1000);
          updateDateTime();
        </script>
      </div>
    </div>
  </div>
</body>