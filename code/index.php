<!doctype html>
<html lang="en" data-bs-theme="auto">
    <head>
        <script src="../assets/js/color-modes.js"></script>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="">
        <title>Demo Authentication</title>
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@docsearch/css@3">
        <link href="../assets/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="../assets/dist/css/sign-in.css" rel="stylesheet">
        <link rel="stylesheet" href="https://icons.getbootstrap.com/assets/font/bootstrap-icons.min.css"><link rel="stylesheet" href="/assets/css/docs.css">

        <script src="./assets/dist/js/crypto-js-4.2.0.js"></script>
        <!-- <script src="./assets/dist/js/jquery-3.7.1.min.js"></script> -->
    </head>
    <script>
        const statistic = [];
        let startTimePassword = 0;

        /**
         * creates a new FIDO2 registration
         * @returns {undefined}
         */
        async function createRegistration() {
            const authenticationType = 1; // 1: registration, 2: login
            const startTime = performance.now()

            try {

                // check browser support
                if (!window.fetch || !navigator.credentials || !navigator.credentials.create) {
                    throw new Error('Browser not supported.');
                }

                // get create args
                let rep = await window.fetch('server.php?fn=getCreateArgs' + getGetParams(), {method:'GET', cache:'no-cache'});
                const createArgs = await rep.json();

                // error handling
                if (createArgs.success === false) {
                    throw new Error(createArgs.msg || 'unknown error occured');
                }

                // replace binary base64 data with ArrayBuffer. a other way to do this
                // is the reviver function of JSON.parse()
                recursiveBase64StrToArrayBuffer(createArgs);

                // create credentials
                const cred = await navigator.credentials.create(createArgs);

                // create object
                const authenticatorAttestationResponse = {
                    transports: cred.response.getTransports  ? cred.response.getTransports() : null,
                    clientDataJSON: cred.response.clientDataJSON  ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
                    attestationObject: cred.response.attestationObject ? arrayBufferToBase64(cred.response.attestationObject) : null
                };

                // check auth on server side
                rep = await window.fetch('server.php?fn=processCreate' + getGetParams(), {
                    method  : 'POST',
                    body    : JSON.stringify(authenticatorAttestationResponse),
                    cache   : 'no-cache'
                });
                const authenticatorAttestationServerResponse = await rep.json();

                // prompt server response
                if (authenticatorAttestationServerResponse.success) {
                    documentAuthentication(authenticationType, startTime, 1, "Registration successful");
                    show_success();
                } else {
                    documentAuthentication(authenticationType, startTime, 0, "Registration canceled");
                }

            } catch (err) {
                documentAuthentication(authenticationType, startTime, 0, "Registration canceled");
            }
        }


        /**
         * checks a FIDO2 registration
         * @returns {undefined}
         */
        async function checkRegistration() {
            const authenticationType = 2; // 1: registration, 2: login
            const startTime = performance.now()
            let loginExists = true;

            try {

                if (!window.fetch || !navigator.credentials || !navigator.credentials.create) {
                    throw new Error('Browser not supported.');
                }

                // get check args
                let rep = await window.fetch('server.php?fn=getGetArgs' + getGetParams(), {method:'GET',cache:'no-cache'});
                const getArgs = await rep.json();

                // error handling
                if (getArgs.success === false) {
                    loginExists = false;
                    createRegistration();
                }

                // replace binary base64 data with ArrayBuffer. a other way to do this
                // is the reviver function of JSON.parse()
                recursiveBase64StrToArrayBuffer(getArgs);

                // check credentials with hardware
                const cred = await navigator.credentials.get(getArgs);

                // create object for transmission to server
                const authenticatorAttestationResponse = {
                    id: cred.rawId ? arrayBufferToBase64(cred.rawId) : null,
                    clientDataJSON: cred.response.clientDataJSON  ? arrayBufferToBase64(cred.response.clientDataJSON) : null,
                    authenticatorData: cred.response.authenticatorData ? arrayBufferToBase64(cred.response.authenticatorData) : null,
                    signature: cred.response.signature ? arrayBufferToBase64(cred.response.signature) : null,
                    userHandle: cred.response.userHandle ? arrayBufferToBase64(cred.response.userHandle) : null
                };

                // send to server
                rep = await window.fetch('server.php?fn=processGet' + getGetParams(), {
                    method:'POST',
                    body: JSON.stringify(authenticatorAttestationResponse),
                    cache:'no-cache'
                });
                const authenticatorAttestationServerResponse = await rep.json();

                // check server response
                if (authenticatorAttestationServerResponse.success) {
                    documentAuthentication(authenticationType, startTime, 1, "Login successful");
                    show_success();
                } else {
                    documentAuthentication(authenticationType, startTime, 0, "Authentication error. Try a different passkeys.");
                }

            } catch (err) {
                if(loginExists) {
                    documentAuthentication(authenticationType, startTime, 0, "Authentication canceled");
                }
            }
        }

        function documentAuthentication(authenticationType, startTime, authenticationStatus, notificationMessage) {
            const durationFinal = duration(startTime, performance.now());
            const statisticFinal = {
                "type": authenticationType, // 1: registration 2: login
                "duration": durationFinal, // duration in milliseconds
                "status": authenticationStatus // 0: authentication failed, 1: authentication successful
            };

            window.fetch('server.php?fn=statistic' + getGetParams(), {method:'POST',body:JSON.stringify(statisticFinal),cache:'no-cache'}).then(function(response) {
                return response.json();
            }).then(function(json) {
            }).catch(function(err) {
                console.log(err);
            });

            statistic.push(statisticFinal);
            sendNotification(notificationMessage);
        }

        function sendNotification(notificationMessage) {
            if(notificationMessage!="") {
                // setTimeout(function() { window.alert(notificationMessage); }, 1);

                const toastLive = document.getElementById('liveToast');
                document.getElementById('toastMessage').innerHTML = notificationMessage;
                
                const toastBootstrap = bootstrap.Toast.getOrCreateInstance(toastLive);
                toastBootstrap.show();
            }
        }

        function duration(startTime, endTime) {
            const duration = endTime - startTime; //in milliseconds
            return duration;
        }

        /**
         * convert RFC 1342-like base64 strings to array buffer
         * @param {mixed} obj
         * @returns {undefined}
         */
        function recursiveBase64StrToArrayBuffer(obj) {
            let prefix = '=?BINARY?B?';
            let suffix = '?=';
            if (typeof obj === 'object') {
                for (let key in obj) {
                    if (typeof obj[key] === 'string') {
                        let str = obj[key];
                        if (str.substring(0, prefix.length) === prefix && str.substring(str.length - suffix.length) === suffix) {
                            str = str.substring(prefix.length, str.length - suffix.length);

                            let binary_string = window.atob(str);
                            let len = binary_string.length;
                            let bytes = new Uint8Array(len);
                            for (let i = 0; i < len; i++)        {
                                bytes[i] = binary_string.charCodeAt(i);
                            }
                            obj[key] = bytes.buffer;
                        }
                    } else {
                        recursiveBase64StrToArrayBuffer(obj[key]);
                    }
                }
            }
        }

        /**
         * Convert a ArrayBuffer to Base64
         * @param {ArrayBuffer} buffer
         * @returns {String}
         */
        function arrayBufferToBase64(buffer) {
            let binary = '';
            let bytes = new Uint8Array(buffer);
            let len = bytes.byteLength;
            for (let i = 0; i < len; i++) {
                binary += String.fromCharCode( bytes[ i ] );
            }
            return window.btoa(binary);
        }

        /**
         * Get URL parameter
         * @returns {String}
         */
        function getGetParams() {
            let url = '';

            url += '&apple=0';
            url += '&yubico=0';
            url += '&solo=0';
            url += '&hypersecu=0';
            url += '&google=0';
            url += '&microsoft=0';
            url += '&mds=0';

            url += '&requireResidentKey=0';

            url += '&type_usb=0';
            url += '&type_nfc=0';
            url += '&type_ble=0';
            url += '&type_int=1';
            url += '&type_hybrid=1';

            url += '&fmt_android-key=0';
            url += '&fmt_android-safetynet=0';
            url += '&fmt_apple=0';
            url += '&fmt_fido-u2f=0';
            url += '&fmt_none=1';
            url += '&fmt_packed=0';
            url += '&fmt_tpm=0';

            url += '&rpId=' + encodeURIComponent('passkey.uversy.com');

            // encodeURIComponent(CryptoJS.enc.Hex.stringify(CryptoJS.lib.WordArray.random(32));
            url += '&userId=' + encodeURIComponent(CryptoJS.SHA512(document.getElementById('email').value)); //Just for demonstration purpose, change in production environment
            url += '&userName=' + encodeURIComponent(document.getElementById('email').value);
            url += '&userDisplayName=' + encodeURIComponent(document.getElementById('email').value);
            // url += '&userDisplayName=' + encodeURIComponent(document.getElementById('name').value);

            url += '&userVerification=required';

            return url;
        }

        // window.onload = function() {
        // }

        function setAttestation(attestation) {
            let inputEls = document.getElementsByTagName('input');
            for (const inputEl of inputEls) {
                if (inputEl.id && inputEl.id.match(/^(fmt|cert)\_/)) {
                    inputEl.disabled = !attestation;
                }
                if (inputEl.id && inputEl.id.match(/^fmt\_/)) {
                    inputEl.checked = attestation ? inputEl.id !== 'fmt_none' : inputEl.id === 'fmt_none';
                }
                if (inputEl.id && inputEl.id.match(/^cert\_/)) {
                    inputEl.checked = attestation ? inputEl.id === 'cert_mds' : false;
                }
            }
        }
        
        const validateEmail = (email) => {
        return String(email)
            .toLowerCase()
            .match(
                /^(([^<>()[\]\\.,;:\s@"]+(\.[^<>()[\]\\.,;:\s@"]+)*)|.(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/
            );
        };

        function hide_element(id) {
            document.getElementById(id).classList.remove('display-block');
            document.getElementById(id).classList.add('display-none');
        }

        function show_element(id) {
            document.getElementById(id).classList.remove('display-none');
            document.getElementById(id).classList.add('display-block');
        }

        function clear_element(id) {
            document.getElementById(id).value = '';
        }

        function show_success() {
            hide_element('email-model');
            hide_element('authentication');
            hide_element('password-model');
            show_element('success');
        }

        function logout() {
            startTimePassword = 0;
            clear_element('password');
            clear_element('email');
            hide_element('success');
            show_element('email-model');
        }

        function passwordAuthentication() {
            if(validateEmail(document.getElementById('email').value)) {
                hide_element('email-model');
                hide_element('authentication');
                show_element('password-model');
                startTimePassword = performance.now();
            } else {
                sendNotification('Please enter a valid email address');
            }
        }

        function validatePassword(password) {
            // Regular expressions for each character class
            const hasUpperCase = /[A-Z]/;
            const hasLowerCase = /[a-z]/;
            const hasNumbers = /\d/;
            const hasSpecialChars = /[\W_]/; // Includes non-word characters and underscores

            // Check minimum length
            if (!password || password.length < 8) {
                return false;
            }

            // Count the number of character classes present
            let classCount = 0;
            if (hasUpperCase.test(password)) classCount++;
            if (hasLowerCase.test(password)) classCount++;
            if (hasNumbers.test(password)) classCount++;
            if (hasSpecialChars.test(password)) classCount++;

            // Check if at least two character classes are present
            return classCount >= 2;
        }

        function enterPassword() {
            let password = document.getElementById('password').value;
            if(validatePassword(password)) {

                window.fetch('server.php?fn=password' + getGetParams(), {method:'POST',body:JSON.stringify({
                    'password': password,
                    'duration': duration(startTimePassword, performance.now()),
                }),cache:'no-cache'}).then(function(response) {
                    return response.json();
                }).then(function(json) {
                    if (json.success) {
                        sendNotification(json.msg);
                        show_success();
                    } else {
                        startTimePassword = performance.now();
                        sendNotification('The password is incorrect, please try again');
                    }
                }).catch(function(err) {
                    startTimePassword = performance.now();
                    console.log(err);
                    sendNotification('Please enter a password with 12 characters from two classes (upper case, lower case, numeric, or special characters)');
                });

            } else {
                sendNotification('Please enter a password with 12 characters from two classes (upper case, lower case, numeric, or special characters)');
            }
        }

        function enterEmail() {
            let email = document.getElementById('email').value;

            if(validateEmail(email)) {
                document.getElementById('email-badge').innerHTML = email;
                hide_element('email-model');
                show_element('authentication');
            } else {
                sendNotification('Please enter a valid email address');
            }
        }

        function passkeyAuthentication() {
            checkRegistration();
        }

    </script>
    <body class="d-flex align-items-center py-4 bg-body-tertiary">
        <main class="form-signin w-100 m-auto">

            <form id="authentication" class="display-none">
                <h1 class="h3 mb-4 fw-normal">Authentication</h1>

                <div class="email-badge-container">
                    <span id="email-badge" class="email-badge">john.doe@mail.com</span>
                </div>

                <p class="lead-text">
                  With passkeys, you don’t need to<br>remember complex passwords.
                </p>

                <ul class="lead-list">
                  <li>
                    <strong>What are passkeys?</strong>
                    <br>Passkeys are encrypted digital keys you create using your fingerprint, face, or screen lock.
                  </li>
                  <li>
                    <strong>Where are passkeys saved?</strong>
                    <br>Passkeys are stored securely on your device. Your fingerprint or face never leaves your device.
                  </li>
                </ul>

                <p class="buttons">
                    <?php
                        $button_passkey = '<button type="button" class="btn btn-primary py-2" onclick="passkeyAuthentication()">Use a Passkey</button>';
                        $button_password = '<button type="button" class="btn btn-primary py-2" onclick="passwordAuthentication()">Use a Password</button>';

                        $random = random_int(1,2);
                        if($random===1) {
                            echo $button_passkey;
                            echo $button_password;
                        } else if($random===2) {
                            echo $button_password;
                            echo $button_passkey;
                        }
                    ?>
                </p>
            </form>

            <form id="email-model" class="form-model">
                <h1 class="h3 mb-4 fw-normal">Authentication</h1>
                <div class="form-floating">
                    <input type="email" class="form-control mb-3" placeholder="" id="email">
                    <label for="email">Email</label>
                </div>

                <p>
                    <button type="button" class="btn btn-primary py-2 w-100" onclick="enterEmail()">Continue</button>
                </p>
            </form>

            <form id="password-model" class="form-model display-none">
                <h1 class="h3 mb-4 fw-normal">Enter your password</h1>
                <div class="form-floating">
                    <input type="password" class="form-control mb-3" placeholder="" id="password">
                    <label for="password">Password</label>
                </div>

                <p>
                    <button type="button" class="btn btn-primary py-2" onclick="enterPassword()">Authenticate</button>
                </p>
            </form>

            <form class="form-model display-none" id="success">
                <h1 class="h3 mb-4 fw-normal">Successful Authentication</h1>
                <span>
                    <i class="bi bi-check-circle-fill"></i>
                </span>
                <p>
                    <button type="button" class="btn btn-primary py-2" onclick="logout()">Logout</button>
                </p>
            </form>

        </main>

        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div id="liveToast" class="toast align-items-center text-bg-primary border-0" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div id="toastMessage" class="toast-body">
                        Message
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>

        <script src="../assets/dist/js/bootstrap.bundle.min.js"></script>
    </body>
</html>