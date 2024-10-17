<!doctype html>
<html lang="ru" data-bs-theme="auto">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Импорт24</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-EVSTQN3/azprG1Anm3QDgpJLIm9Nao0Yz1ztcQTwFspd3yD65VohhpuuCOmLASjC" crossorigin="anonymous">
    <style>
        #loading-spinner {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1100; /* Установим z-index выше, чем у модального окна */
        }
        #overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5); /* Черный цвет с прозрачностью */
            z-index: 1099; /* На уровень выше модального окна */
            cursor: not-allowed; /* Изменение курсора на запрещающий */
        }
        .form-switch {
            padding-left: 0 !important;
        }
    </style>
</head>
<body>
<main class="ps-3 pt-3 pe-3 pb-3">
    <div class="row">
        <div id="alert-container"></div>
    </div>
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">Импорт товаров</div>
                <div class="card-body">
                    <form id="importForm" enctype="multipart/form-data">
                        @csrf
                        <div class="alert alert-danger" role="alert">
                            Обратите внимание, при импорте файла, все товарные позиции будет затерты новыми из импортируемого файла.
                        </div>
                        <div class="mb-3">
                            <label for="formFile" class="form-label">Выберите файл Excel</label>
                            <input class="form-control" type="file" name="file" id="formFile" required>
                        </div>
                        <input type="hidden" name="objectID" value="{{ $objectID }}">
                        <button type="submit" class="btn btn-success w-100">Импортировать</button>
                    </form>
                </div>
            </div>
            <div class="card mt-3">
                <div class="card-header">Экспорт товаров</div>
                <div class="card-body">
                    <form id="importForm" enctype="multipart/form-data">
                        @csrf
                        <div class="alert alert-info" role="alert">
                            Обратите внимание, при экспорте файла, все товарные позиции (включая данные по самому товару) будут экспортированы в <b>XLSX</b> файл.<br>Данный файл после внесения изменений, может быть импортирован обратно.
                        </div>
                        <a type="button" class="btn btn-warning w-100 exportProcess">Экспорт</a>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">

            <ul class="nav nav-pills mb-3 row" id="pills-tab" role="tablist">
                <li class="nav-item col-6" role="presentation">
                    <button class="nav-link active w-100" id="pills-home-tab" data-bs-toggle="pill" data-bs-target="#pills-home" type="button" role="tab" aria-controls="pills-home" aria-selected="true">Техническая поддержка</button>
                </li>
                <li class="nav-item col-6" role="presentation">
                    <button class="nav-link w-100" id="view-settings-tab" data-bs-toggle="pill" data-bs-target="#view-settings" type="button" role="tab" aria-controls="view-settings" aria-selected="false">Настройка отображения</button>
                </li>
            </ul>
            <div class="tab-content" id="pills-tabContent">
                <div class="tab-pane fade show active" id="pills-home" role="tabpanel" aria-labelledby="pills-home-tab" tabindex="0">
                    <div class="card">
                        <div class="card-body">
                            <form id="feedbackForm">
                                <div class="mb-3">
                                    <label for="domain" class="form-label">Домен портала</label>
                                    <input readonly type="text" name="domain" class="form-control" id="domain" value="{{ $domain }}">
                                </div>
                                <div class="mb-3">
                                    <label for="email" class="form-label">Ваш Email</label>
                                    <input type="email" name="email" class="form-control" id="email">
                                </div>
                                <div class="mb-3">
                                    <label for="reason" class="form-label">Тема обращения</label>
                                    <select name="reason" class="form-select" id="reason">
                                        <option value="Обратная связь" selected>Обратная связь</option>
                                        <option value="Требуется доработка">Требуется доработка</option>
                                        <option value="Идеи и предложения">Идеи и предложения</option>
                                        <option value="Возникла ошибка">Возникла ошибка</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="message" class="form-label">Текст обращения</label>
                                    <textarea name="message" class="form-control" id="message" rows="3"></textarea>
                                </div>
                                <button type="button" id="submitBtn" class="btn btn-primary w-100">Отправить</button>
                            </form>
                            <div id="alert-feedback-container" class="mt-3"></div>
                        </div>
                        <div class="card-footer text-body-secondary text-center">
                            или свяжитесь с нами через <a target="_blank" href="https://t.me/meeteam_support">Telegram</a>
                        </div>
                    </div>
                </div>
                <div class="tab-pane fade" id="view-settings" role="tabpanel" aria-labelledby="view-settings-tab" tabindex="0">
                    <div class="card">
                        <div class="card-header">Использование в смарт процессах</div>
                        <div class="card-body">
                            <div style="max-height: 475px; overflow-y: auto;">
                                @if(empty($availablePlacements))
                                    <h6 class="text-center" style="color:red">На портале отсутствуют смарт процессы</h6>
                                @else
                                    @foreach($availablePlacements as $key => $placement)
                                        <div class="form-check form-switch d-flex justify-content-between">
                                            <label class="form-check-label" for="{{ $key }}">{{ $placement['title'] }}</label>
                                            <input class="form-check-input" type="checkbox" id="{{ $key }}" checked disabled>
                                        </div>
                                    @endforeach
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</main>

<div id="overlay" class="d-none"></div>
<div id="loading-spinner" class="d-none">
    <div class="spinner-border text-primary" role="status">
        <span class="visually-hidden">Обработка...</span>
    </div>
</div>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-MrcW6ZMFYlzcLA8Nl+NtUVF0sA7MsXsP1UyJoMp4YLEuNSfAP+JcXn/tWtIaxVXM" crossorigin="anonymous"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script>
    $(document).ready(function () {
        $(document).ready(function () {
            $('#importForm').on('submit', function (e) {
                e.preventDefault();

                var formData = new FormData(this);

                $.ajax({
                    url: '{{ route('import.process') }}',
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    beforeSend: function () {
                        $('#alert-container').html(''); // Очистка предыдущих сообщений
                        $('#loading-spinner').removeClass('d-none'); // Показать индикатор загрузки
                        $('#overlay').removeClass('d-none');
                    },
                    success: function (response) {
                        $('#loading-spinner').addClass('d-none'); // Скрыть индикатор загрузки
                        $('#overlay').addClass('d-none');
                        $('#alert-container').html(
                            '<div class="alert alert-success text-center">' + response.message + '</div>'
                        );
                    },
                    error: function (xhr) {
                        $('#loading-spinner').addClass('d-none'); // Скрыть индикатор загрузки
                        $('#overlay').addClass('d-none');
                        $('#alert-container').html(
                            '<div class="alert alert-danger text-center">' + xhr.responseJSON.message + '</div>'
                        );
                    }
                });
            });

            $('.exportProcess').on('click', function (e) {
                e.preventDefault();
                $.ajax({
                    url: '{{ route('export.process') }}',
                    type: 'GET',
                    processData: false,
                    contentType: false,
                    beforeSend: function () {
                        $('#alert-container').html('');
                        $('#loading-spinner').removeClass('d-none');
                        $('#overlay').removeClass('d-none');
                    },
                    success: function (response) {
                        if (response.download_url) {
                            var link = document.createElement('a');
                            link.href = response.download_url;
                            link.setAttribute('download', '');
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                            $('#loading-spinner').addClass('d-none');
                            $('#overlay').addClass('d-none');
                        } else {
                            $('#alert-container').html(
                                '<div class="alert alert-danger text-center">Ошибка: ссылка на файл не найдена.</div>'
                            );
                        }
                    },
                    error: function (xhr) {
                        $('#loading-spinner').addClass('d-none');
                        $('#overlay').addClass('d-none');
                        $('#alert-container').html('<div class="alert alert-danger text-center">' + xhr.responseJSON.message + '</div>');
                    }
                });
            });

            $('#submitBtn').on('click', function(e) {
                e.preventDefault();

                var formData = {
                    domain: $('#domain').val(),
                    email: $('#email').val(),
                    reason: $('#reason').val(),
                    message: $('#message').val(),
                };

                $.ajax({
                    url: '{{ route("feedback") }}',
                    type: 'POST',
                    data: formData,
                    success: function(response) {
                        $('#feedbackForm')[0].reset();
                        $('#alert-feedback-container').html('<div class="alert alert-success text-center">' + response.message + '</div>');
                    },
                    error: function(xhr) {
                        $('#alert-feedback-container').html('<div class="alert alert-danger text-center">' + xhr.responseJSON.message + '</div>');
                    }
                });
            });

        });

    });
</script>
</body>
</html>
