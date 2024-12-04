<?php
//klasa odpowiadająca za routing czyli przetwarzanie URL zapytania skierowanego do serwera API
//wszystko po localhost/bankAPI trafi dzieki temu do tego skryptu
require_once('Route.php');
//model odpowiadający za tabelę account w bazie danych - umożliwia operacje na rachunkach
require_once('model/Account.php');
//model użytkownika
require_once('model/User.php');
//model tokena
require_once('model/Token.php');
require_once('model/Transfer.php');

//połączenie do bazy danych
//TODO: wyodrębnić zmienne dotyczące środowiska do pliku konfiguracyjnego
$db = new mysqli('localhost', 'root', '', 'bankAPI');
//ustawienie kodowania znaków na utf8 dla bazy danych
$db->set_charset('utf8');

//użyj przestrzeni nazw od klasy routingu i od naszej klasy od rachunków
use Steampixel\Route;
use BankAPI\Account;

//jeśli ktoś zapyta API bez żadnego parametru
//zwróć hello world
//TODO: to jest tylko do testów  - usunąć później
Route::add('/', function() {
  echo 'Hello world!';
});

//ścieżka używana przez aplikację okienkową do logowania
//aplikacja wysyła nam login i hasło zakodowane JSON metodą post
//API odpowiada do aplikacji wysyłając token w formacie JSON
Route::add('/login', function() use($db) {
    $data = file_get_contents('php://input');
    $data = json_decode($data, true);
    $ip = $_SERVER['REMOTE_ADDR'];
    try {
        $id = User::login($data['login'], $data['password'], $db);
        $token = Token::new($ip, $id, $db);
        header('Content-Type: application/json');
        echo json_encode(['token' => $token]);
    } catch (Exception $e) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Invalid login or password']);
    }
}, 'post');

//ścieżka zwracająca szczegóły rachunku
Route::add('/account/details', function() use($db) {
    $data = file_get_contents('php://input');
    $dataArray = json_decode($data, true);
    $token = $dataArray['token'];

    if (!Token::check($token, $_SERVER['REMOTE_ADDR'], $db)) {
        header('HTTP/1.1 401 Unauthorized');
        echo json_encode(['error' => 'Invalid token']);
        return;
    }

    $userId = Token::getUserId($token, $db);
    $accountNo = Account::getAccountNo($userId, $db);
    $account = Account::getAccount($accountNo, $db);

    header('Content-Type: application/json');
    echo json_encode($account->getArray());
}, 'post');

//ścieżka wyświetla dane rachunku bankowego po jego numerze
Route::add('/account/([0-9]*)', function($accountNo) use($db) {
    $account = Account::getAccount($accountNo, $db);
    header('Content-Type: application/json');
    echo json_encode($account->getArray());
});

// Endpoint do wykonywania przelewów
Route::add('/transfer/new', function() use($db) {
  // Pobranie danych z zapytania POST
  $data = file_get_contents('php://input');
  $dataArray = json_decode($data, true);

  // Sprawdzenie, czy dane zostały poprawnie przekazane
  if (!isset($dataArray['token'], $dataArray['target'], $dataArray['amount'])) {
      header('HTTP/1.1 400 Bad Request');
      echo json_encode(['error' => 'Missing required parameters.']);
      return;
  }

  $token = $dataArray['token'];

  // Sprawdzamy ważność tokenu
  if (!Token::check($token, $_SERVER['REMOTE_ADDR'], $db)) {
      header('HTTP/1.1 401 Unauthorized');
      echo json_encode(['error' => 'Invalid token']);
      return;
  }

  // Pobieramy ID użytkownika z tokenu
  $userId = Token::getUserId($token, $db);
  $source = Account::getAccountNo($userId, $db); // Numer konta źródłowego

  // Sprawdzamy saldo konta źródłowego
  $account = Account::getAccount($source, $db);
  $accountArray = $account->getArray();
  $currentBalance = $accountArray['amount'];

  $target = $dataArray['target']; // Numer konta docelowego
  $amount = $dataArray['amount']; // Kwota przelewu

  // Walidacja kwoty przelewu
  if ($amount <= 0) {
      header('HTTP/1.1 400 Bad Request');
      echo json_encode(['error' => 'Amount must be positive.']);
      return;
  }

  // Sprawdzamy, czy konto ma wystarczające środki
  if ($currentBalance < $amount) {
      header('HTTP/1.1 400 Bad Request');
      echo json_encode(['error' => 'Insufficient balance.']);
      return;
  }

  // Wykonujemy przelew
  Transfer::new($source, $target, $amount, $db);
  
  // Zwracamy odpowiedź o powodzeniu
  header('Status: 200');
  echo json_encode(['status' => 'OK']);
}, 'post');

//ta linijka musi być na końcu
Route::run('/bankAPI');
$db->close();
