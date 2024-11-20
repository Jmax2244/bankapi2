<?php
class User {
    //funkcja login zwraca id użytkownika jeżeli login i hasło są poprawne
    //jeśli login i hasło nie są poprawne to funkcja wrzuci wyjątek
    static function login(string $login, string $password, mysqli $db) : int {
        $sql = "SELECT id, passwordHash FROM user WHERE email = ?";
        //przygotuj zapytanie - mysqli prepared statement
        $query = $db->prepare($sql);
        //podmień znaki zapytania na zmienne
        $query->bind_param('s', $login);
        //wykonaj zapytanie
        $query->execute();
        //pobierz wynik
        $result = $query->get_result();
        //jeżeli wynik jest pusty to wrzuć wyjątek
        if($result->num_rows == 0) {
            //nie ma takiej pary użytkownik/hasło
            throw new Exception('Invalid login or password');
        } else {
            //pobierz id użytkownika
            //fetch_assoc zwraca tablicę asocjacyjną z wynikami zapytania
            //$user reprezentuje jeden wiersz z tabeli user
            $user = $result->fetch_assoc();
            $id = $user['id'];
            $hash = $user['passwordHash'];
            //sprawdzić czy hasło jest poprawne
            if(password_verify($password, $hash)) {
                //zwróciło true - hasło jest poprawne
                //zwracamy id użytkownika
                return $id;
            } else {
                //hasło jest niepoprawne
                throw new Exception('Invalid login or password');
            }
        }
    }
}
?>