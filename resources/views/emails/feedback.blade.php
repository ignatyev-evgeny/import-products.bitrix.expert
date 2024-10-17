<!DOCTYPE html>
<html>
<head>
    <title>Новое сообщение с формы обратной связи</title>
</head>
<body>
<h2>Новое сообщение с формы обратной связи</h2>

<p><strong>Домен портала:</strong> {{ $data['domain'] }}</p>
<p><strong>Email отправителя:</strong> {{ $data['email'] }}</p>
<p><strong>Тема обращения:</strong> {{ $data['reason'] }}</p>
<p><strong>Сообщение:</strong> {{ $data['message'] }}</p>
</body>
</html>