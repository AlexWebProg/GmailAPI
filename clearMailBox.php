<?php
/**
 * Удаляем письма в почтовых ящиках Gmail
 * Используем сервисный аккаунт и его ключ
 */
require __DIR__ .'/../general/config/config.php'; // Общий файл конфигурации
require __DIR__ .'/../vendor/autoload.php'; // Загрузчик внешних компонент

// Задаём количества дней хранения почты в ящиках
$arrMailBoxesForClean = array(
    'a@domain.com' => 30,
    'b@domain.com' => 30,
    'c@domain.com' => 7,
    'd@domain.com' => 7,
    'e@domain.com' => 7,
    'f@domain.com' => 1
);

$arrErrors = array(); // Массив ошибок
$objGmailAPI = new GmailAPI(); // Класс для работы с GMail

// Проходим по списку почтовых ящиков, из которых нужно удалить старые письма
foreach ($arrMailBoxesForClean as $strEmail => $intDays) {
    try{
        // Подключаемся к почтовому ящику
        $service = $objGmailAPI->getService($strEmail);
        // Указываем условие выборки писем в почтовом ящике
        $arrParams = array('before' => date('Y/m/d', (time() - 60 * 60 * 24 * $intDays)));
        // Получаем массив писем, подходящих для удаления
        $arrIDs = $objGmailAPI->listMessageIDs($service,$strEmail,$arrParams);
        // Удаляем письма по их ID в массиве $arrIDs
        if (count($arrIDs)) $objGmailAPI->deleteMessages($service,$strEmail,$arrIDs);
        // Удаляем все использованные переменные
        unset($service,$arrIDs);
    }catch (Exception $e) {
        $arrErrors[] = $e->getMessage();
    }
}

if (count($arrErrors)){
    $strTo = 'my_email@domain.com';
    $strSubj = 'Ошибка при удалении старых писем из почтовых ящиков';
    $strMessage = 'При удалении старых писем из почтовых ящиков возникли следующие ошибки:'.
        '<ul><li>'.implode('</li><li>',$arrErrors).'</li></ul>'.
        '<br/>URL: '.filter_input(INPUT_SERVER, 'REQUEST_URI', FILTER_SANITIZE_URL);
    $objMailSender = new mailSender();
    $objMailSender->sendMail($strTo,$strSubj,$strMessage);
}