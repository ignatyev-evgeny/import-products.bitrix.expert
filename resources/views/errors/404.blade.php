<!doctype html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
        <title>404</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
        <link href="https://getbootstrap.com/docs/4.0/examples/sign-in/signin.css" rel="stylesheet">
    </head>
    <body class="text-center">
        <form class="form-signin">
            <img class="mb-4" src="{{ asset('img/error-404.png') }}" alt="" width="144" height="144">
            <h1 class="h3 mb-3 font-weight-normal">{{ $exception->getMessage() }}</h1>
            <button class="btn btn-lg btn-primary btn-block" type="button" onclick="location.reload()">Вернуться на главную</button>
            <p class="mt-5 mb-3 text-muted">&copy; 2024</p>
        </form>
    </body>
</html>
