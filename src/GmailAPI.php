<?php
namespace Alexwebprog\GMailAPI;

// Класс для работы с GMail
use Exception;
use Google\Service\Gmail\{ListLabelsResponse, Message};
use Google_Client;
use Google_Service_Gmail;
use Google_Service_Gmail_BatchDeleteMessagesRequest;
use Google_Service_Gmail_ModifyMessageRequest;

class GmailAPI
{
    private string $credentials_file = __DIR__ . '/../../../Gmail/credentials.json'; // Ключ сервисного аккаунта

    /**
     * Функция возвращает Google_Service_Gmail Authorized Gmail API instance
     *
     * @param string $strEmail Почта пользователя
     * @return Google_Service_Gmail Authorized Gmail API instance
     * @throws Exception
     */
    public function getService(string $strEmail) : Google_Service_Gmail
    {
        // Подключаемся к почтовому ящику
        try {
            $client = new Google_Client();
            $client->setAuthConfig($this->credentials_file);
            $client->setApplicationName('My Super Project');
            $client->setScopes(Google_Service_Gmail::MAIL_GOOGLE_COM);
            $client->setSubject($strEmail);
            $service = new Google_Service_Gmail($client);
        } catch (Exception $e) {
            throw new Exception('Исключение в функции getService: '.$e->getMessage());
        }
        return $service;
    }

    /**
     * Функция возвращает массив ID сообщений в ящике пользователя
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $strEmail Почта пользователя
     * @param  array $arrOptionalParams любые дополнительные параметры для выборки писем
     * Из них мы сделаем стандартную строку поиска в Gmail вида after: 2020/08/20 in:inbox label:
     * и запишем её в переменную q массива $opt_param
     * @return array Массив ID писем или массив ошибок array('arrErrors' => $arrErrors), если они есть
     * @throws Exception
     */
    public function listMessageIDs(
        Google_Service_Gmail $service,
        string $strEmail,
        array $arrOptionalParams = []
    ) : array
    {
        $arrIDs = []; // Массив ID писем

        $pageToken = NULL; // Токен страницы в почтовом ящике
        $messages = []; // Массив писем в ящике

        // Параметры выборки
        $opt_param = [];
        // Если параметры выборки есть, делаем из них строку поиска в Gmail и записываем её в переменную q
        if (count($arrOptionalParams))
            $opt_param['q'] = str_replace('=', ':',
                http_build_query($arrOptionalParams, null, ' '));

        // Получаем массив писем, соответствующих условию выборки, со всех страниц почтового ящика
        do {
            try {
                if ($pageToken) {
                    $opt_param['pageToken'] = $pageToken;
                }
                $messagesResponse = $service->users_messages->listUsersMessages($strEmail, $opt_param);
                if ($messagesResponse->getMessages()) {
                    $messages = array_merge($messages, $messagesResponse->getMessages());
                    $pageToken = $messagesResponse->getNextPageToken();
                }
            } catch (Exception $e) {
                throw new Exception('Исключение в функции listMessageIDs: '.$e->getMessage());
            }
        } while ($pageToken);

        // Получаем массив ID этих писем
        if (count($messages)) {
            foreach ($messages as $message) {
                $arrIDs[] = $message->getId();
            }
        }
        return $arrIDs;
    }

    /**
     * Удаляем сообщения из массива их ID функцией batchDelete
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $strEmail Почта пользователя
     * @param  array $arrIDs массив ID писем для удаления из функции listMessageIDs
     * @throws Exception
     */
    public function deleteMessages(Google_Service_Gmail $service, string $strEmail, array $arrIDs)
    {
        // Разбиваем массив на части по 1000 элементов, так как столько поддерживает метод batchDelete
        $arrParts = array_chunk($arrIDs, 999);
        if (count($arrParts)){
            foreach ($arrParts as $arrPartIDs){
                try {
                    // Получаем объект запроса удаляемых писем
                    $objBatchDeleteMessages = new Google_Service_Gmail_BatchDeleteMessagesRequest();
                    // Назначаем удаляемые письма
                    $objBatchDeleteMessages->setIds($arrPartIDs);
                    // Удаляем их
                    $service->users_messages->batchDelete($strEmail,$objBatchDeleteMessages);
                } catch (Exception $e) {
                    throw new Exception('Исключение в функции deleteMessages: '.$e->getMessage());
                }
            }
        }
    }

    /**
     * Получаем содержимое сообщения функцией get
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $strEmail Почта пользователя
     * @param  string $strMessageID ID письма
     * @param  string $strFormat The format to return the message in.
     * Acceptable values are:
     * "full": Returns the full email message data with body content parsed in the payload field; the raw field is not used. (default)
     * "metadata": Returns only email message ID, labels, and email headers.
     * "minimal": Returns only email message ID and labels; does not return the email headers, body, or payload.
     * "raw": Returns the full email message data with body content in the raw field as a base64url encoded string; the payload field is not used.
     * @param  array $arrMetadataHeaders When given and format is METADATA, only include headers specified.
     * @return  Message
     * @throws Exception
     */
    public function getMessage(
        Google_Service_Gmail $service,
        string $strEmail,
        string $strMessageID,
        string $strFormat = 'full',
        array $arrMetadataHeaders = []
    ) : Message
    {
        $arrOptionalParams = [
            'format' => $strFormat // Формат, в котором возвращаем письмо
        ];
        // Если формат - metadata, перечисляем только нужные нам заголовки
        if (($strFormat == 'metadata') && count($arrMetadataHeaders))
            $arrOptionalParams['metadataHeaders'] = implode(',',$arrMetadataHeaders);

        try {
            return $service->users_messages->get($strEmail, $strMessageID,$arrOptionalParams);
        } catch (Exception $e) {
            throw new Exception('Исключение в функции getMessage: '.$e->getMessage());
        }
    }

    /**
     * Выводим список меток, имеющихся в почтовом ящике
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $strEmail Почта пользователя
     * @return ListLabelsResponse список меток
     * @throws Exception
     */
    public function listLabels(Google_Service_Gmail $service, string $strEmail) : ListLabelsResponse
    {
        try {
            return $service->users_labels->listUsersLabels($strEmail);
        } catch (Exception $e) {
            throw new Exception('Исключение в функции listLabels: '.$e->getMessage());
        }
    }

    /**
     * Добавляем или удаляем метку (флаг) к письму
     *
     * @param  Google_Service_Gmail $service Authorized Gmail API instance.
     * @param  string $strEmail Почта пользователя
     * @param  string $strMessageID ID письма
     * @param  array $arrAddLabelIds Массив ID меток, которые мы добавляем к письму
     * @param  array $arrRemoveLabelIds Массив ID меток, которые мы удаляем в письме
     * @return Message - текущее письмо
     * @throws Exception
     */
    public function modifyLabels(
        Google_Service_Gmail $service,
        string $strEmail,
        string $strMessageID,
        array $arrAddLabelIds = [],
        array $arrRemoveLabelIds = []
    ) : Message
    {
        try {
            $objPostBody = new Google_Service_Gmail_ModifyMessageRequest();
            $objPostBody->setAddLabelIds($arrAddLabelIds);
            $objPostBody->setRemoveLabelIds($arrRemoveLabelIds);
            return $service->users_messages->modify($strEmail,$strMessageID,$objPostBody);
        } catch (Exception $e) {
            throw new Exception('Исключение в функции modifyLabels: '.$e->getMessage());
        }
    }

}
