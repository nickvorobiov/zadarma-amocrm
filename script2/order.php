<?php

// Переменные, которые ты можешь настроить на свой вкус

// Данные для доступа в AMO
// Боевые данные
$amoLogin = '***'; // Ваш логин (электронная почта)
$amoSubdomain = '***'; // http://???.amocrm.ru
$amoKey = '***'; // Хэш для доступа к API (см. в профиле пользователя)

date_default_timezone_set('Europe/Moscow');

// Если true, то выводит на экран отладочные сообщения
$debug = false;

/////////////////////////////////////////////////////
///////// Дальше ты не хочешь ничего менять /////////
/////////////////////////////////////////////////////

header('Content-Type: text/html; charset=utf-8');

// Получаем данные из запроса GET или POST

if (!$_REQUEST || !array_key_exists('phone', $_REQUEST) || strlen($_REQUEST['phone'] == 0)) {
  echo 'Пошли в меня ?phone=xxx, и я вставлю его в AMO';
  die;
} 

// Подключаемся к AMO

$out = amoApiRequest('auth.php?type=json', 'POST', ['USER_LOGIN'=>$amoLogin,'USER_HASH'=>$amoKey]);

// Декодируем JSON ответ

$res = json_decode($out, true);
$res = $res['response'];

// Флаг успешной авторизации доступен в свойстве "auth"

if (!isset($res['auth'])) {
  die("Авторизация в AMO не удалась");
}

if (sendPhoneToAmo($_REQUEST['phone'])) {
  // если debug, то не выводятся на экран сообщения, поэтому выводим ОК, чтобы была хоть какая-то информация
  echo 'OK';
} else {
  echo 'FAIL';
}

// Отправляем номер телефона в АМО
function sendPhoneToAmo($phone) {
  // Формируем лид для api AMO
  $leads['request']['leads']['add'] = [
    [
      'name' => $phone
    ]
  ];
  consoleLog('Сохраняем в AMO номер ' . $phone);

  // Отправляем в АМО
  $out = amoApiRequest('v2/json/leads/set', 'POST', $leads);
  $res = json_decode($out, true);

  // Если удачно отправили, то перемещаем файл в папку result
  if ($res && $res['response'] && $res['response']['leads']) {
    consoleLog('OK');
    return true;
  } else {
    consoleLog($out);
  }

  return false;
}

// Выполняет GET-запрос к AMO
function amoApiRequest($apiMethod, $httpMethod = 'GET', $postFields = null) {
  global $amoSubdomain;

  // API endpoint
  $link = 'https://'.$amoSubdomain.'.amocrm.ru/private/api/'.$apiMethod;
  $curl = curl_init(); // Сохраняем дескриптор сеанса cURL

  // Устанавливаем необходимые опции для сеанса cURL
  curl_setopt($curl,CURLOPT_RETURNTRANSFER,true);
  curl_setopt($curl,CURLOPT_USERAGENT,'amoCRM-API-client/1.0');
  curl_setopt($curl,CURLOPT_URL,$link);

  if ($httpMethod == 'POST') {
    curl_setopt($curl,CURLOPT_CUSTOMREQUEST,'POST');
    curl_setopt($curl,CURLOPT_POSTFIELDS,json_encode($postFields));
    curl_setopt($curl,CURLOPT_HTTPHEADER,array('Content-Type: application/json'));
  }

  curl_setopt($curl,CURLOPT_HEADER,false);
  curl_setopt($curl,CURLOPT_COOKIEFILE,__DIR__.'/cookie.txt');
  curl_setopt($curl,CURLOPT_COOKIEJAR,__DIR__.'/cookie.txt');
  curl_setopt($curl,CURLOPT_SSL_VERIFYPEER,0);
  curl_setopt($curl,CURLOPT_SSL_VERIFYHOST,0);
   
  $out = curl_exec($curl); // Инициируем запрос к API и сохраняем ответ в переменную

  $code = curl_getinfo($curl,CURLINFO_HTTP_CODE); // Получаем код ответа
  curl_close($curl); // Закрываем соединение
  checkCurlResponse($code); // Проверяем, что ответ — не ошибка

  return $out;
}

// Переводит код ошибки CURL на человеческий язык
function checkCurlResponse($code)
{
  $code = (int)$code;
  $errors = array(
    301 => 'Moved permanently',
    400 => 'Bad request',
    401 => 'Unauthorized',
    403 => 'Forbidden',
    404 => 'Not found',
    500 => 'Internal server error',
    502 => 'Bad gateway',
    503 => 'Service unavailable'
  );
  try
  {
    // Если код ответа не равен 200 или 204 - возвращаем сообщение об ошибке
    if( $code != 200 && $code != 204)
      throw new Exception(isset($errors[$code]) ? $errors[$code] : 'Undescribed error',$code);
  }
  catch(Exception $E)
  {
    consoleLog('Ошибка: '.$E->getMessage().PHP_EOL.'Код ошибки: '.$E->getCode());
    die;
  }
}

function consoleLog($text)
{
  global $debug;

  if ($debug) {
    echo date('[Y-m-d H:i:s] ');
    echo "$text\n";
  }
}
