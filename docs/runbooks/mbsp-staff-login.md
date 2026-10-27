# MBSP Staff Login

The adapter follows API LOGIN STAFF (1).pdf and Billing System's
`includes/StaffDirectoryService.php`. Postman is a client for testing the same
HTTP request; the application connects directly to the API.

## Configuration

Populate the `MBSP_STAFF_API_*` variables in the untracked `.env` from the restricted
API document. Never put their values in tracked documentation, screenshots,
exported Postman collections or logs. `INDECS_STAFF_AUTH_DRIVER=mbsp` is the default.

The API URL must use HTTPS. The client uses Basic Auth and form encoding with
three fields: the configured API key as the field name containing the API token,
`nostaff`, and `password`. Password mode defaults to `md5` to match Billing System;
`raw` is available if ICT confirms that protocol. Users always type their actual
password, never a precomputed hash. No staff password or API response is stored.

```powershell
php artisan config:clear
php artisan migrate
php artisan staff:link approved-officer@example.test 12345
```

Replace the example account and number with an existing approved iNDECS officer
and the independently confirmed staff number. The command does not create users,
change roles or overwrite an existing conflicting staff link. It records a console
audit event. Restrict command access to authorised administrators. A user's email
is not used as proof of MBSP identity; roles and department remain managed locally.

## Behaviour

- Staff enter their staff number and MBSP password. Local demo credentials are
  accepted only with driver `local` in local/testing environments, never as fallback.
- Only a successful response with matching NOSTAFF and FLAGPASSWORD valid is accepted.
- FLAGPASSWORD expire blocks login and asks the officer to change the password through MBSP.
- Missing links, inactive users and users without current module access are denied.
- Unavailable or malformed API responses deny login with a controlled message.
- Five failures per staff number/IP within 60 seconds block further requests.
- MBSP login does not issue a persistent remember-me session. Existing sessions
  continue during API outages; no OIDC tokens are issued by this increment.

## Postman Check

Use a private, unsynchronised environment containing the restricted values.
Send POST to the configured URL, choose Basic Auth with the configured service
username/password, and select Body > x-www-form-urlencoded. Set the API-key
field to its token, nostaff to an authorised staff number and password to the
MD5 of the staff password (or raw only when configured/confirmed). Treat that
MD5 value as a credential too. Disable redirects and retain TLS verification.

Expected response: HTTP 200, status success, data[0].NOSTAFF matching the request,
and data[0].FLAGPASSWORD valid. A null response means rejected credentials.
Do not save actual staff passwords or response profiles in exported requests.

## Verification

For a recurring service-unavailable message, run `php artisan staff:diagnose-login`
from a normal local PowerShell session. This checks configuration, TCP and TLS
without sending credentials. A server launched inside a restricted agent
environment can fail outbound requests even when Postman works on the same PC.
Run the development server from that normal session using an unused port:

```powershell
php artisan serve --host=127.0.0.1 --port=8001
```

Open `http://127.0.0.1:8001/login`. Keep certificate verification enabled.
An HTTP 200 diagnostic confirms connectivity only. To check an actual account,
run `php artisan staff:check-login YOUR_STAFF_NUMBER` and enter the real password
at the hidden prompt. Do not pass a password as a command-line argument.
Verify the staff number independently before linking it; a Postman example
does not establish account ownership.

`php artisan test --filter=MbspStaffLoginTest` exercises the request encoding,
authentication header, response validation, rejected/expired/mismatched credentials,
outages, authorisation and lockout using fake HTTP responses. Live verification
must use a real authorised staff account; no sample identity in the PDF is used
as an application account or granted privileges.
