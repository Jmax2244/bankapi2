<?php
namespace BankAPI;

class Transfer {
    /**
     * Processes a new transfer.
     */
    public static function process($data, $db) {
        $token = $data['token'];
        $targetAccountNo = $data['target'];
        $transferAmount = $data['amount'];

        if ($transferAmount <= 0) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Transfer amount must be greater than zero.']);
            return false;
        }

        if (!Token::check($token, $_SERVER['REMOTE_ADDR'], $db)) {
            header('HTTP/1.1 401 Unauthorized');
            echo json_encode(['error' => 'Invalid token.']);
            return false;
        }

        $userId = Token::getUserId($token, $db);
        $accountNo = Account::getAccountNo($userId, $db);
        $account = Account::getAccount($accountNo, $db);

        if ($account->getBalance() < $transferAmount) {
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Insufficient balance for this transfer.']);
            return false;
        }

        // Deduct amount from source account and add to target account
        $account->deductBalance($transferAmount, $db);
        $targetAccount = Account::getAccount($targetAccountNo, $db);
        $targetAccount->addBalance($transferAmount, $db);

        // Save the transfer in the database
        self::saveTransfer($accountNo, $targetAccountNo, $transferAmount, $db);

        return true;
    }

    /**
     * Saves a transfer record in the database.
     */
    private static function saveTransfer($sourceAccount, $targetAccount, $amount, $db) {
        $query = "INSERT INTO transfers (source_account, target_account, amount, created_at) VALUES (?, ?, ?, NOW())";
        $stmt = $db->prepare($query);
        $stmt->bind_param("ssd", $sourceAccount, $targetAccount, $amount);
        $stmt->execute();
    }

    /**
     * Retrieves the list of transfers for a given account.
     */
    public static function getTransfers($accountNo, $db) {
        $query = "SELECT * FROM transfers WHERE source_account = ? ORDER BY created_at DESC";
        $stmt = $db->prepare($query);
        $stmt->bind_param("s", $accountNo);
        $stmt->execute();
        $result = $stmt->get_result();

        $transfers = [];
        while ($row = $result->fetch_assoc()) {
            $transfers[] = $row;
        }
        return $transfers;
    }
}
