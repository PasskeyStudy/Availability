<?php

/*
 * Copyright (C) 2022 Lukas Buchs
 * license https://github.com/lbuchs/WebAuthn/blob/master/LICENSE MIT
 *
 * Server test script for WebAuthn library. Saves new registrations in session.
 *
 *            JAVASCRIPT            |          SERVER
 * ------------------------------------------------------------
 *
 *               REGISTRATION
 *
 *      window.fetch  ----------------->     getCreateArgs
 *                                                |
 *   navigator.credentials.create   <-------------'
 *           |
 *           '------------------------->     processCreate
 *                                                |
 *         alert or or fail      <----------------'
 *
 * ------------------------------------------------------------
 *
 *              VALIDATION
 *
 *      window.fetch ------------------>      getGetArgs
 *                                                |
 *   navigator.credentials.get   <----------------'
 *           |
 *           '------------------------->      processGet
 *                                                |
 *         alert or or fail      <----------------'
 *
 * ------------------------------------------------------------
 */

/*ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);*/

function database() {
    $db = new MysqliDb (Array (
        'host' => '_HOST_',
        'username' => '_USERNAME_', 
        'password' => '_PASSWORD_',
        'db'=> '_DB_',
        'port' => 3306,
        'prefix' => '',
        'charset' => 'utf8',
    ));
    return $db;
}

function initialize_database($db) {
    if (!$db->tableExists ('user')) {
        $table = $db->rawQuery("CREATE TABLE `user` (  `id_user` int(11) NOT NULL,
            `username` varchar(128) NOT NULL,
            `user_display_name` varchar(128) NOT NULL,
            `password` varchar(255) NOT NULL,
            `date` timestamp NOT NULL DEFAULT current_timestamp()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        $index = $db->rawQuery("ALTER TABLE `user`
            ADD PRIMARY KEY (`id_user`);");
        $index = $db->rawQuery("ALTER TABLE `user`
            MODIFY `id_user` int(11) NOT NULL AUTO_INCREMENT;");
    }
    if (!$db->tableExists ('passkey')) {
        $table = $db->rawQuery("CREATE TABLE `passkey` (
            `id_passkey` int(11) NOT NULL,
            `id_user` int(11) NOT NULL,
            `userId` varchar(256) NOT NULL,
            `rpId` varchar(64) NOT NULL,
            `attestationFormat` varchar(32) NOT NULL,
            `credentialId` varchar(128) NOT NULL,
            `credentialPublicKey` varchar(256) NOT NULL,
            `certificateChain` varchar(64) NOT NULL,
            `certificate` varchar(64) NOT NULL,
            `certificateIssuer` varchar(64) NOT NULL,
            `certificateSubject` varchar(64) NOT NULL,
            `signatureCounter` varchar(64) NOT NULL,
            `AAGUID` varchar(64) NOT NULL,
            `rootValid` tinyint(1) NOT NULL,
            `userPresent` tinyint(1) NOT NULL,
            `userVerified` tinyint(1) NOT NULL,
            `date` timestamp NOT NULL DEFAULT current_timestamp()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        $index = $db->rawQuery("ALTER TABLE `passkey`
            ADD PRIMARY KEY (`id_passkey`),
            ADD KEY `id_user_passkey` (`id_user`) USING BTREE;");
        $index = $db->rawQuery("ALTER TABLE `passkey`
            MODIFY `id_passkey` int(11) NOT NULL AUTO_INCREMENT;");
    }
    if (!$db->tableExists ('statistic')) {
        $table = $db->rawQuery("CREATE TABLE `statistic` (
            `id_statistic` int(11) NOT NULL,
            `id_user` int(11) NOT NULL,
            `authentication` tinyint(1) NOT NULL,
            `type` tinyint(1) NOT NULL,
            `duration` int(11) NOT NULL,
            `status` tinyint(1) NOT NULL,
            `date` timestamp NOT NULL DEFAULT current_timestamp()
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        $index = $db->rawQuery("ALTER TABLE `statistic`
            ADD PRIMARY KEY (`id_statistic`),
            ADD KEY `id_user_statistic` (`id_user`) USING BTREE;");
        $index = $db->rawQuery("ALTER TABLE `statistic`
            MODIFY `id_statistic` int(11) NOT NULL AUTO_INCREMENT;");
    }
}

function validatePassword($password) {
    // Regular expressions for each character class
    $hasUpperCase = '/[A-Z]/';
    $hasLowerCase = '/[a-z]/';
    $hasNumbers = '/\d/';
    $hasSpecialChars = '/[\W_]/'; // Includes non-word characters and underscores

    // Check minimum length
    if (strlen($password) < 12) {
        return false;
    }

    // Count the number of character classes present
    $classCount = 0;
    if (preg_match($hasUpperCase, $password)) $classCount++;
    if (preg_match($hasLowerCase, $password)) $classCount++;
    if (preg_match($hasNumbers, $password)) $classCount++;
    if (preg_match($hasSpecialChars, $password)) $classCount++;

    // Check if at least two character classes are present
    return $classCount >= 2;
}

function stringify($input) {
    if($input===null) {
        return "".$input."";
    }
    return $input;
}

function stringify_boolean($input) {
    if($input===null) {
        return 0;
    }
    return $input;
}

function decode_data_rows($data) {
    if(isset($data['credentialId'])) {
        $data['credentialId'] = base64_decode($data['credentialId']);
    }
    if(isset($data['AAGUID'])) {
        $data['AAGUID'] = base64_decode($data['AAGUID']);
    }
    return $data;
}

function decode_data($data) {
    if(is_array($data)) {
        foreach($data as &$row) {
            $row = decode_data_rows($row);
        }
    } else {
        decode_data_rows($data);
    }
    return $data;
}

require_once './vendor/autoload.php';
require_once './src/WebAuthn.php';

try {
    session_start();

    $db = database();
    initialize_database($db);

    // read get argument and post body
    $fn = filter_input(INPUT_GET, 'fn');
    $requireResidentKey = !!filter_input(INPUT_GET, 'requireResidentKey');
    $userVerification = filter_input(INPUT_GET, 'userVerification', FILTER_SANITIZE_SPECIAL_CHARS);

    $userId = filter_input(INPUT_GET, 'userId', FILTER_SANITIZE_SPECIAL_CHARS);
    $userName = filter_input(INPUT_GET, 'userName', FILTER_SANITIZE_SPECIAL_CHARS);
    $userDisplayName = filter_input(INPUT_GET, 'userDisplayName', FILTER_SANITIZE_SPECIAL_CHARS);

    $userId = preg_replace('/[^0-9a-f]/i', '', $userId);
    $userName = mb_strtolower(preg_replace('/[^0-9a-z]/i', '', $userName));
    $userDisplayName = preg_replace('/[^0-9a-z öüäéèàÖÜÄÉÈÀÂÊÎÔÛâêîôû]/i', '', $userDisplayName);

    $post = trim(file_get_contents('php://input'));
    if ($post) {
        $post = json_decode($post, null, 512, JSON_THROW_ON_ERROR);
    }

    if ($fn !== 'getStoredDataHtml') {

        // Formats
        $formats = [];
        if (filter_input(INPUT_GET, 'fmt_android-key')) {
            $formats[] = 'android-key';
        }
        if (filter_input(INPUT_GET, 'fmt_android-safetynet')) {
            $formats[] = 'android-safetynet';
        }
        if (filter_input(INPUT_GET, 'fmt_apple')) {
            $formats[] = 'apple';
        }
        if (filter_input(INPUT_GET, 'fmt_fido-u2f')) {
            $formats[] = 'fido-u2f';
        }
        if (filter_input(INPUT_GET, 'fmt_none')) {
            $formats[] = 'none';
        }
        if (filter_input(INPUT_GET, 'fmt_packed')) {
            $formats[] = 'packed';
        }
        if (filter_input(INPUT_GET, 'fmt_tpm')) {
            $formats[] = 'tpm';
        }

        $rpId = 'localhost';
        if (filter_input(INPUT_GET, 'rpId')) {
            $rpId = filter_input(INPUT_GET, 'rpId', FILTER_VALIDATE_DOMAIN);
            if ($rpId === false) {
                throw new Exception('invalid relying party ID');
            }
        }

        // types selected on front end
        $typeUsb = !!filter_input(INPUT_GET, 'type_usb');
        $typeNfc = !!filter_input(INPUT_GET, 'type_nfc');
        $typeBle = !!filter_input(INPUT_GET, 'type_ble');
        $typeInt = !!filter_input(INPUT_GET, 'type_int');
        $typeHyb = !!filter_input(INPUT_GET, 'type_hybrid');

        // cross-platform: true, if type internal is not allowed
        //                 false, if only internal is allowed
        //                 null, if internal and cross-platform is allowed
        $crossPlatformAttachment = null;
        if (($typeUsb || $typeNfc || $typeBle || $typeHyb) && !$typeInt) {
            $crossPlatformAttachment = true;

        } else if (!$typeUsb && !$typeNfc && !$typeBle && !$typeHyb && $typeInt) {
            $crossPlatformAttachment = false;
        }


        // new Instance of the server library.
        // make sure that $rpId is the domain name.
        $WebAuthn = new lbuchs\WebAuthn\WebAuthn('WebAuthn Library', $rpId, $formats);

        // add root certificates to validate new registrations
        if (filter_input(INPUT_GET, 'solo')) {
            $WebAuthn->addRootCertificates('rootCertificates/solo.pem');
        }
        if (filter_input(INPUT_GET, 'apple')) {
            $WebAuthn->addRootCertificates('rootCertificates/apple.pem');
        }
        if (filter_input(INPUT_GET, 'yubico')) {
            $WebAuthn->addRootCertificates('rootCertificates/yubico.pem');
        }
        if (filter_input(INPUT_GET, 'hypersecu')) {
            $WebAuthn->addRootCertificates('rootCertificates/hypersecu.pem');
        }
        if (filter_input(INPUT_GET, 'google')) {
            $WebAuthn->addRootCertificates('rootCertificates/globalSign.pem');
            $WebAuthn->addRootCertificates('rootCertificates/googleHardware.pem');
        }
        if (filter_input(INPUT_GET, 'microsoft')) {
            $WebAuthn->addRootCertificates('rootCertificates/microsoftTpmCollection.pem');
        }
        if (filter_input(INPUT_GET, 'mds')) {
            $WebAuthn->addRootCertificates('rootCertificates/mds');
        }

    }

    // ------------------------------------
    // request for create arguments
    // ------------------------------------

    if ($fn === 'getCreateArgs') {
        $createArgs = $WebAuthn->getCreateArgs(\hex2bin($userId), $userName, $userDisplayName, 60*4, $requireResidentKey, $userVerification, $crossPlatformAttachment);

        header('Content-Type: application/json');
        print(json_encode($createArgs));

        // save challange to session. you have to deliver it to processGet later.
        $_SESSION['challenge'] = $WebAuthn->getChallenge();

    // ------------------------------------
    // request for get arguments
    // ------------------------------------

    } else if ($fn === 'getGetArgs') {
        $ids = [];

        if ($requireResidentKey) {

            if (!isset($_SESSION['registrations']) || !is_array($_SESSION['registrations']) || count($_SESSION['registrations']) === 0) {
                throw new Exception('we do not have any registrations to check the registration');
            }

        } else {
            $db->where('userId', $userId);
            $passkeys = decode_data($db->get('passkey'));

            if(!empty($passkeys)) {
                foreach ($passkeys as $reg) {
                    if ($reg['userId'] === $userId) {
                        $ids[] = $reg['credentialId'];
                    }
                }
            }

            if (count($ids) === 0) {
                throw new Exception('no registrations for userId ' . $userId);
            }
        }

        $getArgs = $WebAuthn->getGetArgs($ids, 60*4, $typeUsb, $typeNfc, $typeBle, $typeHyb, $typeInt, $userVerification);

        header('Content-Type: application/json');
        print(json_encode($getArgs));

        // save challange to session. you have to deliver it to processGet later.
        $_SESSION['challenge'] = $WebAuthn->getChallenge();

    // ------------------------------------
    // process create
    // ------------------------------------

    } else if ($fn === 'processCreate') {
        $clientDataJSON = base64_decode($post->clientDataJSON);
        $attestationObject = base64_decode($post->attestationObject);
        $challenge = $_SESSION['challenge']; //$passkey['challenge']; 

        $data = $WebAuthn->processCreate($clientDataJSON, $attestationObject, $challenge, $userVerification === 'required', true, false);

        // add user infos
        $data->userId = $userId;
        $data->userName = $userName;
        $data->userDisplayName = $userDisplayName;

        // ---------------------------------------------------------------------------

        $db->where('username', $userName);
        $user = $db->getOne('user');

        if(empty($user)) {
            $sql_data = array(
                'username' => $userName,
                'user_display_name' => $userDisplayName,
            );
            $id_user = $db->insert('user', $sql_data);
        } else {
            $id_user = $user['id_user'];
        }

        $sql_data = array(
            'id_user' => $id_user,
            'rpId' => stringify($data->rpId),
            'attestationFormat' => stringify($data->attestationFormat),
            'credentialId' => base64_encode($data->credentialId),
            'credentialPublicKey' => stringify($data->credentialPublicKey),
            'certificateChain' => stringify($data->certificateChain),
            'certificate' => stringify($data->certificate),
            'certificateIssuer' => stringify($data->certificateIssuer),
            'certificateSubject' => stringify($data->certificateSubject),
            'signatureCounter' => stringify($data->signatureCounter),
            'AAGUID' => base64_encode($data->AAGUID),
            'rootValid' => stringify_boolean($data->rootValid),
            'userPresent' => stringify_boolean($data->userPresent),
            'userVerified' => stringify_boolean($data->userVerified),
            'userId' => $userId,
        );
        $db->insert('passkey', $sql_data);

        // ---------------------------------------------------------------------------

        $msg = 'registration success.';
        // if ($data->rootValid === false) {
        //     $msg = 'registration ok, but certificate does not match any of the selected root ca.';
        // }

        $return = new stdClass();
        $return->success = true;
        $return->msg = $msg;

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------
    // proccess get
    // ------------------------------------

    } else if ($fn === 'processGet') {
        $clientDataJSON = base64_decode($post->clientDataJSON);
        $authenticatorData = base64_decode($post->authenticatorData);
        $signature = base64_decode($post->signature);
        $userHandle = base64_decode($post->userHandle);
        $id = base64_decode($post->id);
        $challenge = $_SESSION['challenge'] ?? '';
        $credentialPublicKey = null;

        $db->where('username', $userName);
        $user = $db->getOne('user');

        $db->where('id_user', $user['id_user']);
        $passkeys = decode_data($db->get('passkey'));

        foreach ($passkeys as $reg) {
            if ($reg['credentialId'] === $id) {
                $credentialPublicKey = $reg['credentialPublicKey'];
                break;
            }
        }

        if ($credentialPublicKey === null) {
            throw new Exception('Public Key for credential ID not found!');
        }

        // if we have resident key, we have to verify that the userHandle is the provided userId at registration
        if ($requireResidentKey && $userHandle !== hex2bin($reg['userId'])) {
            throw new \Exception('userId doesnt match (is ' . bin2hex($userHandle) . ' but expect ' . $reg['userId'] . ')');
        }

        // process the get request. throws WebAuthnException if it fails
        $WebAuthn->processGet($clientDataJSON, $authenticatorData, $signature, $credentialPublicKey, $challenge, null, $userVerification === 'required');

        $return = new stdClass();
        $return->success = true;

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------
    // proccess save statistic
    // ------------------------------------

    } else if ($fn === 'statistic') {
        $db->where('username', $userName);
        $user = $db->getOne('user');

        if(!empty($user)) {

            $type = filter_var($post->type, FILTER_SANITIZE_NUMBER_INT);
            $duration = filter_var($post->duration, FILTER_SANITIZE_NUMBER_INT);
            $status = filter_var($post->status, FILTER_SANITIZE_NUMBER_INT);

            $sql_data = array(
                'id_user' => $user['id_user'],
                'authentication' => 1, // always from a passkey, password statistic is stored within the 'password' key
                'type' => stringify_boolean($type),
                'duration' => stringify_boolean($duration),
                'status' => stringify_boolean($status),
            );

            $id_statistic = $db->insert('statistic', $sql_data);

            $return = new stdClass();
            $return->success = true;

        } else {
            throw new Exception('userName not found!');
        }

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------
    // proccess clear registrations
    // ------------------------------------

    } else if ($fn === 'clearRegistrations') {
        $_SESSION['registrations'] = null;
        $_SESSION['challenge'] = null;

        $return = new stdClass();
        $return->success = true;
        $return->msg = 'all registrations deleted';

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------
    // store or validate the password
    // ------------------------------------

    } else if ($fn === 'password') {

        // validatePassword($password);

        $password = $post->password; // No need for sanitizing the password as it will be hashed
        $duration = filter_var($post->duration, FILTER_SANITIZE_NUMBER_INT);

        if(!validatePassword($password)) {
            throw new Exception('Please enter a password with 12 characters from two classes (upper case, lower case, numeric, or special characters)');
        }

        $db->where('username', $userName);
        $user = $db->getOne('user');

        if(empty($user) || empty($user['password'])) {

            $password_hash = password_hash($password, PASSWORD_ARGON2ID, ["cost" => 9]);

            if(empty($user)) {
                $sql_data = array(
                    'username' => $userName,
                    'user_display_name' => $userDisplayName,
                    'password' => $password_hash,
                );
                $id_user = $db->insert('user', $sql_data);
            } else {
                $sql_data = array(
                    'password' => $password_hash,
                );
                $db->where('username', $userName);
                $id_user = $db->update('user', $sql_data);
            }

            $sql_data = array(
                'id_user' => $id_user,
                'authentication' => 2, // always from a password
                'type' => 1,
                'duration' => stringify_boolean($duration),
                'status' => 1,
            );

            $id_statistic = $db->insert('statistic', $sql_data);

            $return = new stdClass();
            $return->success = true;
            $return->msg = 'Registration successful';

        } else {
            
            if(password_verify($password, $user['password'])) {
                // Password correct

                $sql_data = array(
                    'id_user' => $user['id_user'],
                    'authentication' => 2, // always from a password
                    'type' => 2,
                    'duration' => stringify_boolean($duration),
                    'status' => 1,
                );

                $id_statistic = $db->insert('statistic', $sql_data);

                $return = new stdClass();
                $return->success = true;
                $return->msg = 'Login successful';

            } else {
                // Password incorrect

                $sql_data = array(
                    'id_user' => $user['id_user'],
                    'authentication' => 2, // always from a password
                    'type' => 2,
                    'duration' => stringify_boolean($duration),
                    'status' => 0,
                );

                $id_statistic = $db->insert('statistic', $sql_data);

                throw new Exception('The password is incorrect, please try again');

            }

        }

        header('Content-Type: application/json');
        print(json_encode($return));

    // ------------------------------------
    // display stored data as HTML
    // ------------------------------------

    } else if ($fn === 'getStoredDataHtml') {
        $html = '<!DOCTYPE html>' . "\n";
        $html .= '<html><head><style>tr:nth-child(even){background-color: #f2f2f2;}</style></head>';
        $html .= '<body style="font-family:sans-serif">';

        $passkeys = decode_data($db->get('passkey'));

        if (isset($passkeys) && is_array($passkeys)) {
            $html .= '<p>There are ' . count($passkeys) . ' registrations in this session:</p>';
            foreach ($passkeys as $reg) {
                $html .= '<table style="border:1px solid black;margin:10px 0;">';
                foreach ($reg as $key => $value) {

                    if (is_bool($value)) {
                        $value = $value ? 'yes' : 'no';

                    } else if (is_null($value)) {
                        $value = 'null';

                    } else if (is_object($value)) {
                        $value = chunk_split(strval($value), 64);

                    } else if (is_string($value) && strlen($value) > 0 && htmlspecialchars($value, ENT_QUOTES) === '') {
                        $value = chunk_split(bin2hex($value), 64);
                    }
                    $html .= '<tr><td>' . htmlspecialchars($key) . '</td><td style="font-family:monospace;">' . nl2br(htmlspecialchars($value)) . '</td>';
                }
                $html .= '</table>';
            }
        } else {
            $html .= '<p>There are no registrations in this session.</p>';
        }
        $html .= '</body></html>';

        header('Content-Type: text/html');
        print $html;

    }

} catch (Throwable $ex) {
    // echo $ex;

    $return = new stdClass();
    $return->success = false;
    $return->msg = $ex->getMessage();

    header('Content-Type: application/json');
    print(json_encode($return));
}