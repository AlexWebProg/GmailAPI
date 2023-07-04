<?php
/**
 * Подключаемся к ящику a@domain.com
 * Берём с него письма о том, что наши письма не доставлены: отправитель: mailer-daemon@googlemail.com
 * Проверяем почтовые ящики в этих письмах
 */

use Alexwebprog\GMailAPI\GmailAPI;

$strEmail = 'a@domain.com'; // Здесь имя почтового ящика
$strLabelID = 'Label_2399611988534712153'; // Флаг reportProcessed - устанавливаем при обработке письма

// Параметры выборки
$arrParams = array(
    'from' => 'mailer-daemon@googlemail.com', // Письма об ошибках приходят с этого адреса
    'in' => 'inbox', // Во входящих
    'after' => date('Y/m/d', (time() - 60 * 60 * 24)), // За последние сутки
    'has' => 'nouserlabels' // Без флага
);

$arrErrors = []; // Массив ошибок
$objGmailAPI = new GmailAPI(); // Класс для работы с GMail
$arrClientEmails = []; // Массив адресов электронной почты, на которые не удалось отправить сообщение

try {
    // Подключаемся к почтовому ящику
    $service = $objGmailAPI->getService($strEmail);
    // Находим в нём отчёты за последние сутки о том, что письма не доставлены
    $arrIDs = $objGmailAPI->listMessageIDs($service,$strEmail, $arrParams);
    // Для найденных писем получаем заголовок 'X-Failed-Recipients', в котором содержится адрес, на который пыталось быть отправлено письмо
    if (count($arrIDs)){
        foreach ($arrIDs as $strMessageID){
            // Получаем метаданные письма
            $objMessage = $objGmailAPI->getMessage($service,$strEmail,$strMessageID,'metadata',array('X-Failed-Recipients'));
            // Заголовки письма
            $arrHeaders = $objMessage->getPayload()->getHeaders();
            // Находим нужный
            foreach ($arrHeaders as $objMessagePartHeader){
                if ($objMessagePartHeader->getName() == 'X-Failed-Recipients'){
                    $strClientEmail = strtolower(trim($objMessagePartHeader->getValue()));
                    if (!empty($strClientEmail)) {
                        if (!in_array($strClientEmail, $arrClientEmails)) $arrClientEmails[] = $strClientEmail;
                    }
                    // Помечаем письмо флагом reportProcessed, чтобы не выбирать его в следующий раз
                    $objGmailAPI->modifyLabels($service,$strEmail,$strMessageID,array($strLabelID));
                }
            }
        }
    }
    unset($service,$arrIDs,$strMessageID);
} catch (Exception $e) {
    $arrErrors[] = $e->getMessage();
}

// Если найдены адреса электронной почты, на которые не удалось доставить сообщения, выводим их
if (count($arrClientEmails)) {
    echo('Не удалось доставить письма по адресам электронной почты:
    <ul><li>'.implode('</li><li>',$arrErrors).'</li></ul>');
}

// Если есть ошибки, выводим их
if (count($arrErrors)){
    echo('При обработке отчётов о недоставленных письмах возникли следующие ошибки:
    <ul><li>'.implode('</li><li>',$arrErrors).'</li></ul>');
}