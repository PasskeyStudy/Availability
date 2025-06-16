# A Large-Scale Field Study of Passkey Adoption and Usage

Passkeys are a password-less authentication method to improve cybersecurity. We conducted a user study to investigate the adoption and usage of passkeys in a large-scale field study. The study's results are currently undergoing peer review. We published the source code, codebook, and statistical analyses of SPSS.

We introduce our [Live Prototype](https://passkey.uversy.com/) and explain the [Functionality](#functionality). Subsequently, we outline the [Installation](#installation), the [Codebook](#codebook), and the [Statistical Analyses with SPSS](#SPSS).

## Table of Contents
[1. Live Prototype](#live)  
[2. Functionality](#functionality)  
[3. Installation](#installation)  
[4. Codebook](#codebook)  
[5. Statistical Analyses with SPSS](#SPSS)

<a name="live"/>

## 1. Live Prototype

We deployed a live prototype to demonstrate the implementation and to try out passkeys. The implementation is based on [lbuchs/WebAuthn](https://github.com/lbuchs/WebAuthn).  
**Live Prototype:** [https://passkey.uversy.com](https://passkey.uversy.com/)

**The prototype offers the following features:**
- Register an account and log in with a passkey
- Register an account and log in with a password
- Conduct a user study and document authentication rates and times

**Screenshots of the Live Prototype:**
              
<img width="60%" alt="Authentication Email" src="images/authentication-email.png">  

Figure 1: *Entry of the email for the authentication*

<img width="60%" alt="Authentication Choice" src="images/authentication-choice.png">  

Figure 2: *Authentication page for users to choose between a passkey and password*

<a name="functionality"/>

## 2. Functionality

The implementation offers the following functionality.

### General
* Registration and login are not separated in the user interface to streamline the authentication process, meaning the user can authenticate, and the implementation automatically detects whether it was a first-time registration (and creates a new account) or a login
* The order of both buttons `Use a Password` and `Use a Passkey` are randomized each time the website is loaded (try `F5` on Windows or `Cmd + R` on Mac)

### Passkeys
* First Factor: The stored passkey *(something you have)*
* Second Factor: Either users’ biometrics *(something you are)* or the device PIN *(something you know)*
* Users can register multiple passkeys, as recommended by the [FIDO Alliance](https://fidoalliance.org/passkeys/)
* A single device can be used with multiple accounts

### Passwords
* First Factor: The password *(something you know)*
* Second Factor: The SMS OTP *(something you have)*

*Note: In the user study, we used an SMS OTP as a second factor for passwords. This feature contains proprietary code, which is not included in the live prototype or this repository.*

### Collecting Statistics in a User Study
The implementation collects anonymous usage statistics in a MySQL database from a user study. The SQL table *statistic* collects the following values for every authentication attempt:  
* `authentication` *(boolean)* indicates whether a passkey (`1`) or a password (`2`) has been used
* `type` *(boolean)* indicates whether the authentication was a first-time registration (`1`) or a login (`2`)
* `duration` *(int)* contains the duration of the authentication process in **milliseconds**
* `status` *(boolean)* indicates whether the authentication was unsuccessful (`0`) or successful (`1`)
* `date` *(timestamp)* contains the timestamp of the endpoint of the authentication

<a name="installation"/>

## 3. Installation

We describe the installation process, device support, and requirements in the following.

### Installation Process
The implementation code is in the folder [code](code/).

1. `composer require thingengineer/mysqli-database-class:dev-master`
2. Upload the [source code](code/) to the server  
   For improved security, set up directory protection for `/vendor` (created by composer)
3. Replace the following MySQL settings in the file [server.php](code/server.php):
    * `'host' => '_HOST_'`
    * `'username' => '_USERNAME_'`
    * `'password' => '_PASSWORD_'`
    * `'db'=> '_DB_'`
4. Open `[www.your-domain.com]/index.php`

*Note: The database tables are automatically initialized upon the first authentication in the prototype*

### Device support
Passkeys are available on a variety of devices: (see also [passkeys.dev/device-support](https://passkeys.dev/device-support/))
* Apple iOS 16+ / iPadOS 16+ / macOS Ventura+
* Android 9+
* Microsoft Windows 10+

### Requirements  
* PHP >= 8.0 with [OpenSSL](http://php.net/manual/en/book.openssl.php) and [Multibyte String](https://www.php.net/manual/en/book.mbstring.php)
* Browser with [WebAuthn support](https://caniuse.com/webauthn) (Firefox 60+, Chrome 67+, Edge 18+, Safari 13+)
* PHP [Sodium](https://www.php.net/manual/en/book.sodium.php) (or [Sodium Compat](https://github.com/paragonie/sodium_compat) ) for [Ed25519](https://en.wikipedia.org/wiki/EdDSA#Ed25519) support
* MySQL database

<a name="codebook"/>

## 4. Codebook

We outline the codebook based on the qualitative survey results from both phases as follows.   

### Phase 1 Codebook

| **Category**                                | **Codes**                                                                                                                                                                                 |
|---------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **Motivating Factors of Passkeys**          | No Memorization<br>Fast Authentication<br>Enhanced Security<br>No Password Creation and Policy<br>Multifactor Ease<br>No Password Resets<br>Other                                         |
| **Concerns of Passkeys**                    | Habit of Using Passwords<br>Lack of Experience or Knowledge<br>Lack of Perceived Benefit<br>Passkey Misconceptions<br>Account Recovery Concerns<br>Multiple Clients<br>Past Negative Experiences<br>Deferred Adoption<br>Privacy and Security Concerns<br>Other |
| **Barriers to Passkey Adoption**            | Passkey Misconceptions<br>Passcode Misconceptions<br>Manual Passkey Activation<br>Perceived Complexity<br>Account Recovery Concerns<br>Multiple Clients<br>Password Manager Integration<br>Biometric Misconceptions<br>Technical Difficulties<br>Other |
| **Barriers to Adopting Passwords with 2FA** | SMS-OTP<br>Password-policy issues<br>Time out<br>Other                                                                                                                                    |

### Phase 2 Codebook

| **Category**                           | **Codes**                                                                                              |
|----------------------------------------|--------------------------------------------------------------------------------------------------------|
| **Reasons for Unsuccessful Logins**    | Unsuccessful Synchronization<br>Device Dependency<br>Technical Difficulties<br>Other                   |
| **Encountered Challenges**             | Passkey Sharing<br>Immutable Storage Location<br>Non-uniform Authentication UI<br>Perceived Loss of Control |
| **Reasons for Unsuccessful Recoveries**| SMS-OTP<br>Other                                                                                       |

<a name="SPSS"/>

## 5. Statistical Analyses with SPSS

We analyzed all quantitative data with the statistical software `SPSS` and published the results in the folder [SPSS](SPSS/).

The study is split into two phases. Phase 1 investigates the adoption of passkeys, and Study 2 explores the usage of passkeys. The participants in Phase 2 are a subset of those in Phase 1. In both phases, we have a group that uses passkeys (Group K) and a group that uses passwords with 2FA (Group W).

### Phase 1 (Passkey Adoption)
In total, there were `5,057` participants in Phase 1: `2,950` in Group K with passkeys and `2107` with passwords with 2FA   
* Participants' gender: [Gender](SPSS/phase1/gender.pdf)
* Participants' age: [Age](SPSS/phase1/age.pdf)
* Participants' education: [Education](SPSS/phase1/education.pdf)
* Participants' Affinity for Technology Interaction (ATI): [ATI](SPSS/phase1/ati.pdf)
* Participants' privacy concerns: [Privacy Concerns](SPSS/phase1/privacy-concerns.pdf)
* Participants' Computer Science (CS) background: [CS background](SPSS/phase1/cs-background.pdf)
* Participants' prior passkey experience: [Prior Exp](SPSS/phase1/prior-exp.pdf)
* Participants' operating system (OS): [OS](SPSS/phase1/os.pdf)
* System Usability Scale (SUS) of authentication method: [SUS](SPSS/phase1/sus.pdf)
* Acceptance scale of authentication method: [Acceptance](SPSS/phase1/acceptance.pdf)

### Phase 2 (Passkey Usage)
* Participants' gender: [Gender](SPSS/phase2/gender.pdf)
* Participants' age: [Age](SPSS/phase2/age.pdf)
* Participants' education: [Education](SPSS/phase2/education.pdf)
* Participants' Affinity for Technology Interaction (ATI): [ATI](SPSS/phase2/ati.pdf)
* Participants' privacy concerns: [Privacy Concerns](SPSS/phase2/privacy-concerns.pdf)
* Participants' Computer Science (CS) background: [CS background](SPSS/phase2/cs-background.pdf)
* Participants' prior passkey experience: [Prior Exp](SPSS/phase2/prior-exp.pdf)
* Participants' operating system (OS): [OS](SPSS/phase2/os.pdf)
* Participants' storage location of passkeys: [Storage](SPSS/phase2/storage.pdf)
* Participants' two-factor authentication method with passkeys: [2FA](SPSS/phase2/2fa.pdf)
* System Usability Scale (SUS) of authentication method: [SUS](SPSS/phase2/sus.pdf)
* Acceptance scale of authentication method: [Acceptance](SPSS/phase2/acceptance.pdf)