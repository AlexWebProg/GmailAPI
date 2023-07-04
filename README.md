# GmailAPI
Набор функций для работы с API Gmail при помощи сервисного аккаунта

### Установка
```
composer require alexwebprog/gmailapi
```

### Настройка

1. Необходимо настроить аккаунт G Suite, как описано в статье https://habr.com/ru/post/516408/
2. Скопировать json-ключ сервисного аккаунта в GMail/credentials.json

### Содержимое пакета

- src/GmailAPI.php - основной класс, содержащий набор функций для взаимодействия с API Gmail
- examples/clearMailBox.php - пример удаления старых сообщений из указанных почтовых ящиков
- examples/unDeliveredMes.php - пример, находящий и обрабатывающий отчёты о не доставленных письмах

