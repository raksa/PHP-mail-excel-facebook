<div class="container">
    <span class="bg-primary">Hi, <?php echo $profile['name']; ?></span>
    <span><a class="btn btn-warning" href="logout.php">Logout</a></span>
</div>
<?php

$PROFILE_ID = 'id';
$PROFILE_EMAIL = 'email';
$PROFILE_NAME = 'name';
$SESSION_EXCEL_MESSAGE = 'excel_message';
$SESSION_UPLOAD_MESSAGE = 'upload_message';
$SESSION_DB_MESSAGE = 'db_message';
$SESSION_MAIL_MESSAGE = 'mail_message';
if (!isset($profile[$PROFILE_ID]) || !isset($profile[$PROFILE_EMAIL]) || !isset($profile[$PROFILE_NAME]))
    die('No profile information');

$file_name = 'excel';
if (isset($_FILES[$file_name]) && isset($_POST['email'])) {

    $_SESSION[$SESSION_EXCEL_MESSAGE] = null;
    $_SESSION[$SESSION_UPLOAD_MESSAGE] = null;
    $_SESSION[$SESSION_DB_MESSAGE] = null;
    $_SESSION[$SESSION_MAIL_MESSAGE] = null;

    function sentMail($toMail, $attach, $profile)
    {
        $email = getenv('EMAIL');
        $password = getenv('EMAIL_PASSWORD');
        if (!$email || !$password) {
            return false;
        }
        $mail = new PHPMailer(true);
        $mail->IsSMTP();
        try {
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = 'ssl';
            $mail->Host = 'smtp.gmail.com';
            $mail->Port = 465;
            $mail->Username = $email;
            $mail->Password = $password;

            $mail->setFrom($email);
            $mail->addAddress($toMail);
            $mail->addAttachment($attach);
            $mail->Subject = 'Uploaded Excel file';
            $mail->Body = 'User: fb-id="' . $profile['id'] .
                '", fb-user-name="' . $profile['name'] . '", fb-email="' . $profile['email'] . '"';

            $mail->Send();
            return true;
        } catch (phpmailerException $e) {
            echo $e->errorMessage();
            return false;
        } catch (Exception $e) {
            echo $e->getMessage();
            return false;
        }
    }

    function isValidExcel($inputFile)
    {
        $types = array('Excel2007', 'Excel5');
        foreach ($types as $type) {
            $reader = PHPExcel_IOFactory::createReader($type);
            if ($reader->canRead($inputFile)) {
                return true;
            }
        }
        return false;
    }

    function isUploaded($file, $fileName)
    {
        $result = array();
        $info = pathinfo($fileName);
        $ext = $info['extension'];
        $name = $info['filename'];
        $i = 0;
        $suf = '';
        $dist = getcwd() . '/../' . 'files/';
        error_log($dist);
        do {
            $newFileName = $name . $suf . '.' . $ext;
            $suf = '_' . $i++;
        } while (file_exists($dist . $newFileName));
        if (!file_exists($dist)) {
            mkdir($dist, 0755, true);
        }
        $result['success'] = move_uploaded_file($file, $dist . $newFileName);
        $result['fileName'] = $newFileName;
        $result['dist_fileName'] = $dist . $newFileName;
        return $result;
    }

    function readDataFromExcel($inputFile)
    {
        $result = array();
        try {
            $output = '';
            $inputFileType = PHPExcel_IOFactory::identify($inputFile);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($inputFile);
            $allSheet = $objPHPExcel->getAllSheets();
            foreach ($allSheet as $worksheet) {
                $highestRow = $worksheet->getHighestRow();
                $highestColumn = $worksheet->getHighestColumn();
                $highestColumnIndex = PHPExcel_Cell::columnIndexFromString($highestColumn);
                for ($row = 1; $row <= $highestRow; $row++) {
                    for ($columnIndex = 0; $columnIndex <= $highestColumnIndex; $columnIndex++) {
                        $cell = $worksheet->getCellByColumnAndRow($columnIndex, $row);
                        $val = $cell->getValue();
                        if ($val) $output .= $val . " ;";
                    }
                    $output .= ' | ';
                }
            }
            $result['success'] = true;
            $result['data'] = $output;
        } catch (Exception $e) {
            $result['success'] = false;
        }
        return $result;
    }

    $toMail = $_POST['email'];
    if (!$_FILES[$file_name]['error']) {
        $inputFile = $_FILES[$file_name]['name'];
        $inputTmpFile = $_FILES[$file_name]['tmp_name'];
        $extension = strtoupper(pathinfo($inputFile, PATHINFO_EXTENSION));
        if ($extension == 'XLSX' || $extension == 'XLS') {
            if (isValidExcel($inputTmpFile)) {
                $data = readDataFromExcel($inputTmpFile);
                if ($data['success']) {
                    $uploaded = isUploaded($_FILES[$file_name]['tmp_name'], $inputFile);
                    if ($uploaded['success']) {
                        $_SESSION[$SESSION_UPLOAD_MESSAGE] = 'Excel file have been uploaded';
                        $db = connect();
                        if ($db) {
                            $userDb = getUserById($db, $profile[$PROFILE_ID]);
                            if (count($userDb) == 0)
                                insertUser($db, $profile[$PROFILE_ID], $profile[$PROFILE_EMAIL], $profile[$PROFILE_NAME]);
                            $_SESSION[$SESSION_DB_MESSAGE] = 'File\'s content ' .
                                (insertExcel($db, $profile[$PROFILE_ID], $uploaded['fileName'], substr($data['data'], 0, 200)) ?
                                    'have been' : 'can\'t be') .
                                ' saved into database.';
                            $db->close();
                        } else {
                            $_SESSION[$SESSION_DB_MESSAGE] = 'Can\'t connect to database.';
                        }
                        $profileSend = array('id' => $profile[$PROFILE_ID], 'name' => $profile[$PROFILE_NAME],
                            'email' => $profile[$PROFILE_EMAIL]);
                        $_SESSION[$SESSION_MAIL_MESSAGE] = 'Excel file ' .
                            (sentMail($toMail, $uploaded['dist_fileName'], $profileSend) ? 'have been' : 'can\'t be') .
                            ' sent to ' . $toMail;
                    } else {
                        $_SESSION[$SESSION_UPLOAD_MESSAGE] = 'Excel file can\'t be uploaded.';
                    }
                } else {
                    $_SESSION[$SESSION_EXCEL_MESSAGE] = 'Fail to read file\'s content.';
                }
            } else {
                $_SESSION[$SESSION_EXCEL_MESSAGE] = 'Invalid file format.';
            }
        } else {
            $_SESSION[$SESSION_EXCEL_MESSAGE] = 'File is not Excel type.';
        }
    } else {
        $_SESSION[$SESSION_EXCEL_MESSAGE] = 'File is error.';
    }
//    header("Location: ./");
}
?>

<div class="container">
    <div class="row">
        <div class="col-md-6">
            <p
                class="label-info"> <?php if (isset($_SESSION[$SESSION_EXCEL_MESSAGE])) echo $_SESSION[$SESSION_EXCEL_MESSAGE]; ?> </p>
            <p
                class="label-info"> <?php if (isset($_SESSION[$SESSION_UPLOAD_MESSAGE])) echo $_SESSION[$SESSION_UPLOAD_MESSAGE]; ?> </p>
            <p
                class="label-info"> <?php if (isset($_SESSION[$SESSION_DB_MESSAGE])) echo $_SESSION[$SESSION_DB_MESSAGE]; ?> </p>
            <p
                class="label-info"> <?php if (isset($_SESSION[$SESSION_MAIL_MESSAGE])) echo $_SESSION[$SESSION_MAIL_MESSAGE]; ?> </p>
        </div>
    </div>
</div>