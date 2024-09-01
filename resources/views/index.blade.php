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
    </style>
</head>
<body>
<main>
    <div class="col-lg-6 mx-auto my-3">
        <div id="alert-container"></div>
        <div class="d-grid gap-2 d-sm-flex justify-content-sm-center">
            <button type="button" class="btn btn-primary btn-lg px-4 gap-3" data-bs-toggle="modal" data-bs-target="#importModal">Импорт</button>
            <a href="{{ asset('storage/import.xlsx') }}" download class="btn btn-outline-secondary btn-lg px-4">Пример</a>
        </div>
    </div>
</main>

<div class="modal fade" id="importModal" tabindex="-1" aria-labelledby="importModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="importModalLabel">Загрузить файл для импорта</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="importForm" enctype="multipart/form-data">
                    @csrf
                    <div class="mb-3">
                        <label for="formFile" class="form-label">Выберите файл Excel</label>
                        <input class="form-control" type="file" name="file" id="formFile" required>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
                        <button type="submit" class="btn btn-primary">Импортировать</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

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
                        $('#importModal').modal('hide');
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
