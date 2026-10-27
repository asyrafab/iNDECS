# Public Access and Google Setup

Implemented routes:

| Access | Route | Identity |
|---|---|---|
| MBSP staff/officer | `/login` | MBSP credentials and approved staff link |
| Public email/password | `/portal/register`, `/portal/login` | Public account with verified email |
| Google | `/portal/google`, `/portal/google/callback` | Verified Google profile, public permissions only |
| No registration | `/portal/email` | Email link; guest/shadow identity without a password |
| Password recovery | `/portal/forgot-password` | Single-use reset link delivered by email |

Public identity is ready for the future submission workflow. Public business
submission forms, case receipts and ongoing case emails are not implemented yet.
The portal reports that online submission is not open. Staff processing routes
remain inaccessible to all public and guest users.

## Google Administrator Settings

Create an OAuth client in **Google Cloud Console / Google Auth Platform**, not
just a Google Workspace user. A Workspace administrator may additionally need
to allow the client under their organisation's API access policies.

1. Create/select the council-owned project for iNDECS.
2. In Branding, set app name `iNDECS`, a monitored user-support email and developer
   contact. Add the production homepage, privacy-policy and terms URLs when those
   pages exist. Do not enter invented policy URLs.
3. Choose audience **External** because members of the public need access.
   Internal would restrict access to your organisation. Add test users while the
   app is in Testing; complete the production publishing/verification steps Google
   requests before opening it to everyone.
4. Request only `openid`, `email`, `profile`. No Gmail, Drive or offline-access
   permission is needed. Email is sent through iNDECS SMTP, not the user's Gmail.
5. Under Clients, create an OAuth 2.0 client of type **Web application**.
6. Add this exact local authorised redirect URI:

   `http://localhost:8000/portal/google/callback`

7. Add the production redirect URI using your real deployed domain:

   `https://YOUR-INDECS-DOMAIN/portal/google/callback`

   Replace the placeholder; do not register it literally. Scheme, hostname, port
   and path must match the configured URI. Use localhost consistently locally;
   127.0.0.1 is a different host. Production uses HTTPS.
8. JavaScript origins are not required for this server-side redirect flow. If the
   console requires an origin for another integration, use the actual origin
   without a callback path; this application does not use the browser Google SDK.
9. Store the issued client ID and secret in the server's environment, not Git or
   public JavaScript. Leave secrets out of screenshots and documentation.

```dotenv
GOOGLE_CLIENT_ID=your-web-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/portal/google/callback
```

Then run `php artisan config:clear` locally (rebuild cached configuration during
deployment). Google login stays visibly disabled until ID and secret are set.
Use a separate OAuth client for production if ICT policy requires environment
isolation. Client secrets remain on the server.

Sources, checked 2026-09-21:
- [Google web-server OAuth setup](https://developers.google.com/identity/protocols/oauth2/web-server)
- [Google production brand verification](https://developers.google.com/identity/protocols/oauth2/production-readiness/brand-verification)
- [Google OAuth policies](https://developers.google.com/identity/protocols/oauth2/policies)
- [Laravel Socialite](https://laravel.com/framework/docs/socialite)

## Email Settings

For local development, Compose includes Mailpit: SMTP `127.0.0.1:1025`, inbox
`http://localhost:8025`, without credentials. Only loopback ports are exposed.
Local mail is captured and is not sent to the real recipient. Run:

```powershell
docker compose up -d mailpit
php artisan migrate
php artisan serve --host=127.0.0.1 --port=8000
```

Production needs MBSP-approved SMTP credentials, sender domain and TLS settings.
Configure `MAIL_MAILER=smtp`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`,
`MAIL_PASSWORD`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME` for that
server. Set `APP_URL` to the public HTTPS origin so links reach users correctly.
Do not deploy Mailpit or the development `.test` sender as production email.

Tokens expire after 15 minutes and are single-use. A GET previews confirmation;
a CSRF-protected POST consumes the token. Only SHA-256 hashes are stored in
`core.public_access_tokens`. Password reset revokes outstanding links. Existing
staff accounts never receive public login/reset links. Tokens must also be
redacted from production reverse-proxy/access logs; application code does not
log them. Schedule deletion of expired token rows as routine housekeeping.

Google identifies users by its subject ID. An email collision does not auto-link:
the owner first signs in via email, then chooses to link Google from their account.
Public provisioning never sets officer roles, departments or staff numbers.

## Remaining Work

Live Google sign-in depends on your client configuration; production email depends
on SMTP setup. The tests fake Google and notifications. Phone OTP, actual public
application forms, ownership links to applications, submission receipts and case
correspondence remain subsequent work. A guest's email must be verified and its
user ID attached to a submission before case data can be shown or emailed.
