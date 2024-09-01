<script src="//api.bitrix24.com/api/v1/"></script>
<script>
    BX24.installFinish();
    BX24.init(function(){
        console.log('Инициализация завершена!', BX24.isAdmin());
    });
</script>
Приложение успешно установлено