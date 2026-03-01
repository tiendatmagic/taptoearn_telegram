<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>HELLO WORLD</title>
    <link rel=preconnect href="//fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Quicksand:wght@300;400;700&display=swap" rel="stylesheet">

    <style>
      body,
      html {
        height: 100%;
        margin: 0
      }

      body {
        background:  linear-gradient(-45deg, #ee7752, #e73c7e, #23a6d5, #23d5ab);
        background-size: 400% 400%;
        height: 100vh;
        position: relative;
        color: white;
        font-family: 'Quicksand', sans-serif;
        font-size: 25px;
        display: flex;
        justify-content: center;
        align-items: center;
        animation: gradient 5s linear infinite;
      }
      @keyframes gradient {
        0% {
          background-position: 0% 50%;
        }
        50% {
          background-position: 100% 50%;
        }
        100% {
          background-position: 0% 50%;
        }
      }

    </style>
  </head>
<body>
    <div class=bg-gradient>
        <h1>HELLO WORLD !</h1>
    </div>
  </body>

</html>