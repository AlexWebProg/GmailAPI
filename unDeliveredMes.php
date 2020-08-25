<?php
/*
 * Подключаемся к ящику a@domain.com
 * Берём с него письма о том, что наши письма не доставлены: отправитель: mailer-daemon@googlemail.com
 * Проверяем почтовые ящики в этих письмах. Если они есть у клиентов на нашем сайте, отправляем на b@domain.com
 * письмо об этом
 */
require __DIR__ .'/../general/config/config.php'; // Общий файл конфигурации
require __DIR__ .'/../vendor/autoload.php'; // Загрузчик внешних компонент

$strEmail = 'a@domain.com';
$strLabelID = 'Label_2399611988534712153'; // Флаг reportProcessed - устанавливаем при обработке письма

// Параметры выборки
$arrParams = array(
    'from' => 'mailer-daemon@googlemail.com', // Письма об ошибках приходят с этого адреса
    'in' => 'inbox', // Во входящих
    'after' => date('Y/m/d', (time() - 60 * 60 * 24)), // За последние сутки
    'has' => 'nouserlabels' // Без флага
);

$arrErrors = array(); // Массив ошибок
$objGmailAPI = new GmailAPI(); // Класс для работы с GMail
$arrClientEmails = array(); // Массив адресов электронной почты, на которые не удалось отправить сообщение

try{
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
                    $strClientEmail = mb_strtolower(trim($objMessagePartHeader->getValue()), 'UTF-8');
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
}catch (Exception $e) {
    $arrErrors[] = $e->getMessage();
}

// Если найдены адреса электронной почты, на которые не удалось доставить сообщения, проверяем их в базе
if (count($arrClientEmails)) {
    $objClients = new clients();
    // Получаем все email всех клиентов
    $arrAllClientsEmails = $objClients->getAllEmails();

    foreach ($arrClientEmails as $strClientEmail){
        $arrUsages = array();
        foreach ($arrAllClientsEmails as $arrRow){
            if (strpos($arrRow['email'], $strClientEmail) !== false) {
                $arrUsages[] = 'как основной email клиентом "<a href="'.MANAGEURL.'?m=admin&sm=clients&edit='.$arrRow['s_users_id'].'">'.$arrRow['name'].'</a>"';
            }
            if (strpos($arrRow['email2'], $strClientEmail) !== false) {
                $arrUsages[] = 'как дополнительный email клиентом "<a href="'.MANAGEURL.'?m=admin&sm=clients&edit='.$arrRow['s_users_id'].'">'.$arrRow['name'].'</a>"';
            }
            if (strpos($arrRow['site_user_settings_contact_email'], $strClientEmail) !== false) {
                $arrUsages[] = 'как контактный email клиентом "<a href="'.MANAGEURL.'?m=admin&sm=clients&edit='.$arrRow['s_users_id'].'">'.$arrRow['name'].'</a>"';
            }
        }
        $intUsagesCnt = count($arrUsages);
        if ($intUsagesCnt > 0){
            $strMessage = 'Не удалось доставить письмо с сайта по адресу электронной почты <span style="color: #000099;">'.$strClientEmail.'</span><br/>
                Этот адрес используется';
            if ($intUsagesCnt == 1){
                $strMessage .= ' '.$arrUsages[0].'<br/>';
            }else{
                $strMessage .= ':<ul>';
                foreach ($arrUsages as $strUsage){
                    $strMessage .= '<li>'.$strUsage.'</li>';
                }
                $strMessage .= '</ul>';
            }
            $strMessage .= '<br/>Пожалуйста, уточните у клиента актуальность этого адреса электронной почты.<br/><br/>
                Это письмо было отправлено автоматически, не отвечайте на него';

            $objMailSender = new mailSender();
            $objMailSender->sendMail('b@domain.com','Проверьте email клиента',$strMessage);
        }
    }
}