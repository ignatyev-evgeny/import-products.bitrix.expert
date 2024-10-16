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
<main class="ps-5 pt-3 pe-5 pb-3">
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
                        <a href="{{ route('export.process') }}" class="btn btn-warning w-100">Экспорт</a>
                    </form>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card ">
                <div class="card-header">Использование в смарт процессах</div>
                <div class="card-body">
                    <div style="max-height: 475px; overflow-y: auto;">
                        @foreach($availablePlacements as $key => $placement)
                            <div class="form-check form-switch d-flex justify-content-between">
                                <label class="form-check-label" for="{{ $key }}">{{ $placement['title'] }}</label>
                                <input class="form-check-input" type="checkbox" id="{{ $key }}" checked disabled>
                            </div>
                        @endforeach
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
        });

    });
</script>
</body>
</html>
