<?php

// Print the response as plain text so that the gateway can read it
header('Access-Control-Allow-Origin: ' . "*");
header('Content-type: text/plain');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE, OPTIONS');
header('Access-Control-Max-Age: 1000');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

/* local db configuration */
$host = getenv("DB_HOST");
$port = getenv("DB_PORT");
$dbname = getenv("DB_NAME");
$user = getenv("DB_USER");
$password = getenv("DB_PASS");
$sms_key = getenv("SMS_API_KEY");
$sms_base_url = getenv("SMS_BASE_URL");

//Connecting to database so that we can get db instance connection or return 
try {
    $dbConn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $dbConn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (Exception $e) {
    echo " Mutwihaganire habaye ikibazo muri sisteme mwongere mukanya";
    return;
}

// Get the parameters provided by MISTA 
$phone = addCountryPrefix($_POST["msisdn"]);
$session_id = $_POST["sessionId"];
$userinput = urldecode($_POST["UserInput"]);
$serviceCode = addHashTagSuffix($_POST["serviceCode"]);
$networkCode = $_POST["networkCode"];


$ussd_string_exploded = explode("16", $userinput);


// Get menu level from ussd_string reply
$level = $ussd_string_exploded[count($ussd_string_exploded) - 1];
if ($userinput == "*662*800*16#") {
    $response = display_menu();
    $ContinueSession = 1;
} else {
    $temp = explode('*', $level);
    $level_1 = str_replace("#", '', $temp[1]);
    switch ($level_1) {
        case 1:
            // If user selected 1 send them to the registration menu
            $res_temp = register($level, $phone, $dbConn);
            $response = $res_temp['msg'];
            if ($res_temp['status'] == 0) {
                $ContinueSession = 0;
            } else {
                $ContinueSession = 1;
            }
            break;
        case 2:
            //If user selected 2, send them to the about menu
            $response = about();
            $ContinueSession = 0;
            break;
        case 3:
            //If user selected 3, send them to the setting menu
            $res_temp = login($level, $dbConn, $phone);
            $response = $res_temp['msg'];
            if ($res_temp['status'] == 0) {
                $ContinueSession = 0;
            } else {
                $ContinueSession = 1;
            }
            break;
        case 4:
            $res_temp = changePin($level, $dbConn, $phone);
            $response = $res_temp['msg'];
            if ($res_temp['status'] == 0) {
                $ContinueSession = 0;
            } else {
                $ContinueSession = 1;
            }
            break;
        default:
            $response = " Wahisemo Ibitaribyo Ongera!!!";
            $ContinueSession = 0;
            break;
    }
}

//This is the home menu function
function display_menu()
{
    $initial_msg = " Murakaza Neza Kuri Sisiteme Y'Iminsi Igihumbi Y'umwana \n\n 1. kwiyandikisha(umubyeyi) \n 2. ibyerekeye sisiteme \n 3. konti yange \n 4. Guhindura umubare wibanga\n";
    return $initial_msg; // add \n so that the menu has new lines
}

// Function that hanldles About menu
function about()
{
    $about_text = " 1 -Iminsi Igihumbi ni gahunda izajya ifasha ababyeyi kugira amakuru ahagije kumikurire yabana bari munsi y'imyaka\n 
    2 - umubyeyi abona amakuru uko yabana bwamwandikishijeho ndetse ninyingo. \n
    3 - umubyeyi ashobora kubaza ikibazo.    
    ";
    return $about_text;
}

function display_user_menu()
{
    $ussd_text = " Ibijyanye na konti yajye \n\n 1. Kureba abana bakwanditseho \n  2. Tanga igitekerezo cyagwa ikibazo\n 3. Gusohoka muri system\n 4. Subira ahabanza \n";
    return $ussd_text;
}

function login($level, $dbConn, $phone)
{
    $temp = explode('*', $level);
    $lvl = trim(str_replace("#", '', $temp[count($temp) - 1]));
    $res = array();

    switch (count($temp)) {
        case 2:
            $res["msg"] = " injiza umubare wibanga:";
            $res["status"] = 1;
            break;
        case 3:
            $pin = $lvl;
            if (empty(trim($lvl))) {
                $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                $res["status"] = 0;
            } else {
                try {
                    $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone' and pin='$pin'");
                    $total_rows = $search_result->rowCount();
                    if ($total_rows == 0) {
                        $res["msg"] = " Umubare w'ibanga ntabwo ariwo.";
                        $res["status"] = 0;
                    } else {
                        $res["msg"] = display_user_menu();
                        $res["status"] = 1;
                    }
                } catch (PDOException $e) {
                    $res["msg"] = " habaye ikibazo, mwongere mukanya";
                    $res["status"] = 0;
                }
            }
            break;
        case 4:
            if (empty($lvl)) {
                $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                $res["status"] = 0;
            } else {
                $resSel = resSelectedMenu($lvl, $dbConn, $phone);
                $res = array_merge($res, $resSel);
            }
            break;
        case 5:
            if (empty($lvl)) {
                $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                $res["status"] = 0;
            } else {
                $resSel = toggleUserMenus($temp, $dbConn, $phone, $lvl);
                $res = array_merge($res, $resSel);
            }
            break;
            // case 6:
            //     if (empty($lvl)) {
            //         $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
            //         $res["status"] = 0;
            //     } else {
            //         $res["msg"] = " igitsina cyumwana \n 1.gabo \n 2.gore ";
            //         $res["status"] = 1;
            //     }
            //     break;
            // case 7:
            //     if (empty($lvl)) {
            //         $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
            //         $res["status"] = 0;
            //     } else if (ctype_digit($lvl) != 1) {
            //         $res["msg"] = " hemewe imibare gusa";
            //         $res["status"] = 0;
            //     } else {
            //         $res["msg"] = " andikamo ibiro umwana yavukanye mumagaramu(gram).urugero: 1500(1.5kg)\n";
            //         $res["status"] = 1;
            //     }
            //     break;
            // case 8:
            //     if (empty($lvl)) {
            //         $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
            //         $res["status"] = 0;
            //     } else if (ctype_digit($lvl) != 1) {
            //         $res["msg"] = " hemewe imibare gusa";
            //         $res["status"] = 0;
            //     } else {
            //         $res["msg"] = " andikamo ibiro umwana afite ubu mumagaramu(gram).urugero: 2000(2kg)\n";
            //         $res["status"] = 1;
            //     }
            //     break;
            // case 9:
            //     if (empty($lvl)) {
            //         $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
            //         $res["status"] = 0;
            //     } else if (ctype_digit($lvl) != 1) {
            //         $res["msg"] = " hemewe imibare gusa";
            //         $res["status"] = 0;
            //     } else {
            //         $res["msg"] = " andikamo aho umwana yavukiye(aderesi).urugero:kicukiro centre de saint\n";
            //         $res["status"] = 1;
            //     }
            //     break;
            // case 10:
            //     if (empty($lvl)) {
            //         $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
            //         $res["status"] = 0;
            //     } else {
            //         $res["msg"] = " Injiza itariki yivuka. urugero:\n " . date("Y-m-d") . (" itariki igomba kuba iri munsi yimyaka ibiri uhereye ubu") . "\n";
            //         $res["status"] = 1;
            //     }
            //     break;
            // case 11:
            //     if (empty($lvl)) {
            //         $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
            //         $res["status"] = 0;
            //     } else if (strtotime($lvl) < strtotime('-2 years')) {
            //         $res["msg"] = " Umwana agomba kuba ari munsi yimyaka 2.";
            //         $res["status"] = 0;
            //     } else if ($lvl > date('Y-m-d')) {
            //         $res["msg"] = " Mwashyizemo itariki itaragera.";
            //         $res["status"] = 0;
            //     } else {
            //         $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone'");
            //         $fetched_rows = $search_result->fetch();
            //         $pid = $fetched_rows['id'];
            //         $first_name = trim(str_replace("#", '', $temp[count($temp) - 7]));
            //         $second_name = trim(str_replace("#", '', $temp[count($temp) - 6]));
            //         $gender = trim(str_replace("#", '', $temp[count($temp) - 5]));
            //         $born_weight = ((int) trim(str_replace("#", '', $temp[count($temp) - 4]))) / 1000;
            //         $current_weight = (int) trim(str_replace("#", '', $temp[count($temp) - 3])) / 1000;
            //         $born_addres = trim(str_replace("#", '', $temp[count($temp) - 2]));
            //         $born = trim(str_replace("#", '', $temp[count($temp) - 1]));

            //         $search_result_not = $dbConn->query("SELECT * FROM events");
            //         $search_result_data = $search_result_not->fetchAll();

            //         if (count($search_result_data) > 0) {
            //             foreach ($search_result_data as $x => $y) {
            //                 $timetosend = $y[2] + time();
            //                 $smstext = $y[0];
            //                 try {
            //                     $dbConn->exec("INSERT INTO schedulers (receiver, message, time_to_be_sent) VALUES('$phone', '$smstext', '$timetosend')");
            //                 } catch (PDOException $e) {
            //                     $res["msg"] = " habaye ikibazo, mwongere mukanya";
            //                     $res["status"] = 0;
            //                 }
            //             }
            //         }
            //         if ((int)$gender == 1) {
            //             $gender = "Umuhungu";
            //         } else {
            //             $gender = "Umukobwa";
            //         }
            //         try {
            //             $search_result = $dbConn->query("SELECT * FROM childrens WHERE parent_id='$pid' AND first_name='$first_name' AND second_name='$second_name'");
            //             $fetched_rows = $search_result->fetch();
            //             if (count($search_result_data) > 0) {
            //                 $res["msg"] = " Umwana ufite aya mazina! " . $first_name . "  .$second_name . asanzwe akanditse muri sisiteme ntabwo byakunze kumwandika";
            //                 $res["status"] = 0;
            //             } else {
            //                 $dbConn->exec("INSERT INTO childrens (first_name, second_name, gender, parent_id, born_date, born_address,born_weight,current_weight) VALUES('$first_name', '$second_name', '$gender', '$pid', '$born' , '$born_addres', $born_weight, $current_weight)");

            //                 //Send Sms and record sent sms response from mista api
            //                 $smsInfo =  "Muraho , umwana yanditswe muri sisiteme yiminsi igihumbi y'umwana . mubyeyi, muzajya muhabwa inama kumikurire ya " . $first_name .  "Murakoze!";

            //                 $sms = SendSms($phone, $smsInfo);
            //                 if ($sms && $sms != null) {
            //                     $cost = $sms['cost'];
            //                     $ref = $sms['uid'];
            //                     $receiver = $sms['to'];
            //                     $status = $sms['status'];
            //                     $dbConn->exec("INSERT INTO message (ref,cost,receiver,status) VALUES('$ref',$cost,'$receiver','$status')");
            //                 }

            //                 $res["msg"] = " Byegenze neza! " . $first_name . " yanditswe muri sisiteme";
            //                 $res["status"] = 0;
            //             }
            //         } catch (PDOException $e) {
            //             $res["msg"] = " habaye ikibazo, mwongere mukanya";
            //             $res["status"] = 0;
            //         }
            //     }
            //     break;
        default:
            $res["msg"] = " habaye ikibazo, mwongere mukanya";
            $res["status"] = 0;
            break;
    }
    return $res;
}

// register is func used to show and register parent menu
function register($level, $phone, $dbConn)
{
    $temp = explode('*', $level);
    $lvl = trim(str_replace("#", '', $temp[count($temp) - 1]));
    $res = array();

    $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone'");
    $total_rows = $search_result->rowCount();
    if ($total_rows > 0) {
        $res["msg"] = " Murakoze iyi $phone nimero mwakoresheje isanzwe iri muri sisiteme!";
        $res["status"] = 0;
    } else {
        switch (count($temp)) {
            case 2:
                $res["msg"] = " Andika izina rya mbere:";
                $res["status"] = 1;
                break;
            case 3:
                if (empty($lvl)) {
                    $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                    $res["status"] = 0;
                } else if (ctype_alpha($lvl) != 1) {
                    $res["msg"] = " Hemewe inyuguti gusa";
                    $res["status"] = 0;
                } else {
                    $res["msg"] = " Andika andi mazina yawe:";
                    $res["status"] = 1;
                }
                break;

            case 4:
                if (empty($lvl)) {
                    $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                    $res["status"] = 0;
                } else {
                    $res["msg"] = " Andika aho ubarizwa(kuntara-kukagari):";
                    $res["status"] = 1;
                }
                break;
            case 5:
                if (empty($lvl)) {
                    $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                    $res["status"] = 0;
                } else {
                    $res["msg"] = " Andika inumero y'indangamuntu:";
                    $res["status"] = 1;
                }
                break;
                // else if (ctype_alpha($lvl) != 1) {
                //     $res["msg"] = " Hemewe inyuguti gusa";
                //     $res["status"] = 0;
                // } 
            case 6:
                if (empty($lvl)) {
                    $res["msg"] = " Ntakintu mwinjijemo ntabwo byemewe";
                    $res["status"] = 0;
                } else if ((ctype_digit($lvl) != 1) || (strlen($lvl) != 16)) {
                    $res["msg"] = " Hemewe imibare 16";
                    $res["status"] = 0;
                }
                $search_result = $dbConn->query("SELECT * FROM guardians WHERE id_card_number='$lvl' AND  phone='$phone'");
                $total_rows = $search_result->rowCount();
                if ($total_rows > 0) {
                    $res["msg"] = " Iyi nimero yindangamuntu mwakoresheje isanzwe yandikishijwe kuri iyi nimero: $phone muri sisiteme!";
                    $res["status"] = 0;
                } else {
                    $res["msg"] = " Hitamo umubare w'ibanga:";
                    $res["status"] = 1;
                }
                break;
            case 7:
                if (empty($lvl)) {
                    $res["msg"] = " Ntakintu mwinjijemo ntabwo byemewe";
                    $res["status"] = 0;
                } else {
                    $res["msg"] = " Emeza umubare w'ibanga wongera uwushyiremo:";
                    $res["status"] = 1;
                }
                break;
            case 8:
                if (empty($lvl)) {
                    $res["msg"] = " Ntakintu mwinjijemo ntabwo byemewe";
                    $res["status"] = 0;
                } else if (trim(str_replace("#", '', $temp[count($temp) - 2])) != trim(str_replace("#", '', $temp[count($temp) - 1]))) {
                    $res["msg"] = " Umubare w'i ibanga ntuhuye nuwo winjije bwambere";
                    $res["status"] = 0;
                } else {
                    $first_name = trim(str_replace("#", '', $temp[count($temp) - 6]));
                    $second_name = trim(str_replace("#", '', $temp[count($temp) - 5]));
                    $address = trim(str_replace("#", '', $temp[count($temp) - 4]));
                    $idno = trim(str_replace("#", '', $temp[count($temp) - 3]));
                    $pin = trim(str_replace("#", '', $temp[count($temp) - 1]));
                    $phone = $phone;
                    $parent_id = uniqid() . '-' . random_int(10000, 99999);
                    $timestamp = time();
                    $createdAt = date("Y-m-d H:i:s", $timestamp);

                    // build sql statement
                    try {
                        $dbConn->exec("INSERT INTO guardians (first_name,second_name,id_card_number,phone,pin,address,parent_id,created_at,updated_at) VALUES('$first_name','$second_name','$idno','$phone','$pin','$address','$parent_id','$createdAt','$createdAt')");

                        //Send Sms and record sent sms response from mista api
                        $smsInfo = "Muraho  " . $first_name . ",kwiyandikisha byagenze neza. mushobora kwandikisha umwana wanyu muri sisiteme yiminsi igihumbi y'umwana mukajya mubona inama kumikurire myiza yumwana nigahunda yinkingo Murakoze!";

                        $sms = SendSms($phone, $smsInfo);
                        if ($sms && $sms != null) {
                            $cost = $sms['cost'];
                            $ref = $sms['uid'];
                            $receiver = $sms['to'];
                            $status = $sms['status'];
                            $dbConn->exec("INSERT INTO messages (ref,cost,receiver,status) VALUES('$ref',$cost,'$receiver','$status')");
                        }

                        $res["msg"] = " kwiyandikisha byagenze neza murakira ubutumwa bw'ikaze mukanya. Murakoze!";
                        $res["status"] = 0;
                    } catch (PDOException $e) {
                        if ($e->getCode() == "23000" && strpos($e->getMessage(), "guardians_id_card_number_unique") !== false) {
                            $res["msg"] = " Inimero yindangamuntu mwakoresheje isanzwe yandikishijwe kuri iyi nimero: $phone muri sisiteme !";
                            $res["status"] = 0;
                        } else {
                            $res["msg"] = " habaye ikibazo, mwongere mukanya";
                            $res["status"] = 0;
                        }
                    }
                }
                break;
            default:
                $res["msg"] = " habaye ikibazo, mwongere mukanya";
                $res["status"] = 0;
                break;
        }
    }
    return $res;
}

function resSelectedMenu($lvl, $dbConn, $phone)
{
    switch ($lvl) {
        case 1:
            // get parent id
            $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone'");
            $fetched_rows = $search_result->fetch();
            $pid = $fetched_rows['id'];
            $comb_res = " Abana Bakwanditseho:\n";

            // get childrens under this->parent
            $child_result = $dbConn->query("SELECT * FROM childrens WHERE parent_id='$pid'");
            if ($child_result->rowCount() < 1) {
                $res["msg"] = " Nta mwana ukwandikishijeho.";
                $res["status"] = 0;
                break;
            } else {
                $i = 1;
                while ($child_fetched_rows = $child_result->fetch()) {
                    $comb_res .= "\n" . $i . ". " . $child_fetched_rows['first_name'] . " " . $child_fetched_rows['second_name'];
                    $i += 1;
                }
            }
            $res["msg"] = $comb_res;
            $res["status"] = 1;
            break;
        case 2:
            $res["msg"] = " Andika igitekerezo cyagwa Ikibazo cyawe:";
            $res["status"] = 1;
            break;
        case 3:
            $res["msg"] = " Murakoze gukoresha sisitemu.";
            $res["status"] = 0;
            break;
        case 4:
            $res["msg"] = display_menu();
            $res["status"] = 1;
            break;
        default:
            $res["msg"] = " habaye ikibazo, mwongere mukanya";
            $res["status"] = 0;
            break;
    }
    return $res;
}
//For change parent pin
function changePin($level, $dbConn, $phone)
{
    $temp = explode('*', $level);
    $lvl = trim(str_replace("#", '', $temp[count($temp) - 1]));
    $res = array();
    switch (count($temp)) {
        case 2:
            $res["msg"] = " injiza umubare wibanga usanzwe:";
            $res["status"] = 1;
            break;
        case 3:
            $pin = $lvl;
            if (empty(trim($lvl))) {
                $res["msg"] = " ntakintu mwinjijemo ntabwo byemewe";
                $res["status"] = 0;
            } else {
                try {
                    $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone' and pin='$pin'");
                    $total_rows = $search_result->rowCount();
                    if ($total_rows == 0) {
                        $res["msg"] = " Umubare w'ibanga ntabwo ariwo.";
                        $res["status"] = 0;
                    } else {
                        $res["msg"] = " Hitamo umubare w'ibanga mushya:";
                        $res["status"] = 1;
                    }
                } catch (PDOException $e) {
                    $res["msg"] = " habaye ikibazo, mwongere mukanya";
                    $res["status"] = 0;
                }
            }
            break;
        case 4:
            // check if the first pin level is empty
            if (empty($lvl)) {
                // if empty, display an error message
                $res["msg"] = " Ntakintu mwinjijemo ntabwo byemewe";
                $res["status"] = 0;
            } else {
                // prompt the user to enter the second pin level
                $res["msg"] = " Emeza umubare w'ibanga mushya:";
                $res["status"] = 1;
            }
            break;
        case 5:
            // check if the second pin level is empty
            if (empty($lvl)) {
                // if empty, display an error message
                $res["msg"] = " Ntakintu mwinjijemo ntabwo byemewe";
                $res["status"] = 0;
            } else {
                // remove any # characters and leading/trailing whitespace from the second-to-last element in the array
                $pin1 = trim(str_replace("#", '', $temp[count($temp) - 2]));
                // check if the second pin level matches the first pin level
                if ($pin1 != $lvl) {
                    // if they don't match, display an error message
                    $res["msg"] = " Umubare w'i ibanga ntuhuye nuwo winjije bwambere";
                    $res["status"] = 0;
                } else {
                    // if they match, update the pin in the database
                    $pin = $lvl;
                    $phone = $phone;
                    try {
                        $dbConn->exec("UPDATE guardians SET pin=$pin WHERE phone=$phone");
                        $res["msg"] = " Murakoze guhindura umubare wawe wibanga byukunze!";
                        $res["status"] = 0;
                    } catch (PDOException $e) {
                        $res["msg"] = " habaye ikibazo, mwongere mukanya";
                        $res["status"] = 0;
                    }
                }
            }
            break;
        default:
            $res["msg"] = " habaye ikibazo, mwongere mukanya";
            $res["status"] = 0;
            break;
    }
    return $res;
}

//ToggleUserMenu Show parent register child menu
function toggleUserMenus($level, $dbConn, $phone, $txt)
{
    $res = array();
    switch ($level[count($level) - 2]) {
        case 1:
            // get parent id
            try {
                $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone'");
                $fetched_rows = $search_result->fetch();
                $pid = $fetched_rows['id'];
                try {
                    // get childrens under this->parent
                    $child_result = $dbConn->query("SELECT * FROM childrens WHERE parent_id='$pid'");
                    $i = 0;
                    while ($child_fetched_rows = $child_result->fetch()) {
                        if ($child_fetched_rows[$txt - 1] == $child_fetched_rows[$i]) {
                            $res["msg"] = childInfo($child_fetched_rows, $dbConn);
                        }
                        $i += 1;
                    }
                    $res["status"] = 1;
                } catch (PDOException $e) {
                    $res["msg"] = " habaye ikibazo, mwongere mukanya";
                    $res["status"] = 0;
                }
            } catch (PDOException $e) {
                $res["msg"] = " habaye ikibazo, mwongere mukanya";
                $res["status"] = 0;
            }
            break;
        case 2:
            $search_result = $dbConn->query("SELECT * FROM guardians WHERE phone='$phone'");
            $fetched_rows = $search_result->fetch();
            $parent_id = $fetched_rows['id'];
            $msg = trim(str_replace("#", '', $level[count($level) - 1]));
            try {
                $dbConn->exec("INSERT INTO chat_rooms (parent_id,sender, message) VALUES('$parent_id','$parent_id','$msg')");
                $res["msg"] = " Murakoze! igitekerezo cyanyu cyakiriwe";
                $res["status"] = 0;
            } catch (PDOException $e) {
                $res["msg"] = " habaye ikibazo, mwongere mukanya";
                $res["status"] = 0;
            }
            break;
        default:
            $res["msg"] = " habaye ikibazo, mwongere mukanya";
            $res["status"] = 0;
            break;
    }
    return $res;
}
//Send Sms this will be responsible to send and return response
function SendSms($phone, $sms)
{
    global $sms_key;
    global $sms_base_url;
    // sms api
    $curl = curl_init();

    curl_setopt_array(
        $curl,
        array(
            CURLOPT_URL => $sms_base_url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => array('to' => $phone, 'from' => 'ThousandDay', 'unicode' => '0', 'sms' => $sms, 'action' => 'send-sms'),
            CURLOPT_HTTPHEADER => array(
                "x-api-key:$sms_key"
            ),
        )
    );

    $responses =  curl_exec($curl);

    if (curl_error($curl)) {
        // There was an error executing the cURL request
        echo 'Error: ' . curl_error($curl);
        return null;
    } else {
        // The cURL request was successful
        $responseData = json_decode($responses, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            // The response was successfully decoded
            if (isset($responseData['data'])) {
                return $responseData['data'];
            } else {
                echo "No Data response" . $responseData;
                return null;
            }
        } else {
            // There was an error decoding the response
            echo 'Error: ' . json_last_error_msg();
            return null;
        }
    }

    curl_close($curl);
}

//Add +25 prefix on phone number if it doesn't
function addCountryPrefix($phone)
{
    // If it does, return the phone number as-is
    return substr($phone, 0, 1) != '+' ? (substr($phone, 0, 1) == '%' ? str_replace("%2B", '+', $phone) : "+" . $phone) : $phone;
}

//addHashTagSuffix will add # to the end of the string if it doesn't otherwise it will return the string as-is
function addHashTagSuffix($string)
{
    // Check if the string ends with "#"
    if (substr($string, -1) !== "#") {
        // If it does not, add the suffix
        return $string . "#";
    }
    // If it does, return the string as-is
    return $string;
}

//Calculate age used to calculate age with the given time
function calcAges($date)
{
    $birthday = new DateTime($date); // Birthdate
    $today = new DateTime(); // Current date/time
    $age = $today->diff($birthday);
    return $age->format('%y umwaka, %m amezi, and %d iminsi');
}

//ChildInfo function used wrapper up children infomation with her/his corresponding vaccine
function ChildInfo($child_fetched_rows, $dbConn)
{
    $res = " Imyirondoro ya " . $child_fetched_rows['first_name'] . " :\n\n" .
        "- Amazina: " . $child_fetched_rows['first_name'] . " " . $child_fetched_rows['second_name'] . "\n." .
        "- Ibiro afite: " . $child_fetched_rows['current_weight'] / 1000 . " (kg).\n" .
        "- Aho yavukiye: " . $child_fetched_rows['born_address'] . ".\n" .
        "- Igihe yavukiye: " . $child_fetched_rows['born_date'] . ".\n" .
        "- Imyaka afite: " . calcAges($child_fetched_rows['born_date']) . ".\n";


    $child_id = $child_fetched_rows['id'];

    $stmt = $dbConn->prepare("SELECT * FROM child_vaccines WHERE child_id=:child_id");
    $stmt->bindValue(':child_id', $child_id, PDO::PARAM_INT);

    // check if the query was successful
    if ($stmt->execute()) {
        $child_vaccine_result = $stmt->fetchAll();
        if ($child_vaccine_result && count($child_vaccine_result) > 0) {
            $cout = count($child_vaccine_result);
            $res = $res . "- Inkingo amaze gufata: $cout";
        } else {
            $res = $res . "- Inkingo amaze gufata: 0";
        }
    } else {
        // handle the error
        echo "Error executing query: " . $stmt->error;
        $res = " habaye ikibazo, mwongere mukanya";
    }

    return $res;
}
# close the pdo connection
$dbConn = null;

$resp = array("sessionId" => $session_id, "message" => $response, "ContinueSession" => $ContinueSession);



echo  json_encode($resp);
