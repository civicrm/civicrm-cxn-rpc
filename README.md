Civi\Cxn\Rpc
------------

Civi\Cxn\Rpc implements an RPC mechanism based on X.509 and JSON.
Generally, it is based on a assymetric business relationship between two
groups:

 * "Sites" are online properties owned by end-user organizations. They
   represent an organization's canonical data-store.  There are many sites.
   In the tests and comments, we will refer to an example site
   called "SaveTheWhales.org".
 * "Applications" are online properties with value-added services. They
   supplement the sites.  There are only a few applications, and they must
   certified to go live.  In the tests and comments, we will refer to an
   example service called "AddressCleanup.com".

Protocol v0.2
-------------

There are two message exchanges:

 * Sites may register (or unregister) with applications. Applications
   should generally accept new registrations. During registration,
   the site generates a shared secret (AES-256 key) and sends it to
   the application (using the app's RSA public-key). Only the
   target application can decode the registration message.
 * Applications may send API calls to sites. In accordance with Civi
   API, these are framed as entity+action+params. All API calls
   are encrypted with the shared secret (AES-256). Only the
   site and application know this shared secret.

Some considerations:

 * Messages can be passed over HTTP, HTTPS, or any other medium. Passing messages over HTTPS is preferrable (because HTTPS supports more protocols and features, such as forward-secrecy), but even with HTTP all interctions will be encrypted.
 * Certificates are validated using the CiviCRM CA. This seems better than trusting a hundred random CA's around the world -- there's one point of failure [rather than a hundred points of failure](http://googleonlinesecurity.blogspot.com/2015/03/maintaining-digital-certificate-security.html).
 * If the CA were compromised, it would affect one's ability to negotiate new connections, but it wouldn't affect the security of existing connections. The CA cannot discover or change the shared-secret.
 * Sites do not need certificates. Only applications need certificates, and the number of applications is relatively small. Therefore, we don't need automated certificate enrollment. This significantly simplifies the technology and riskness of operating the CA.

Protocol v0.1
-------------

Never published.

Base Classes
------------

When creating a new agent, one should use one of these four helper classes:

 * RegistrationClient: A site uses this to establish a connection to a
   an application.
 * RegistrationServer: An application uses this to accept registrations
   from sites.
 * ApiClient: An application uses this to send API calls to the site.
 * ApiServer: A site uses this to accept API calls from an application.

Policy Recommendations
----------------------

 * Applications should accept new registrations. If a registration is
   attempted with an existing cxnId, then use the shared-secret as
   client authentication -- if the shared-secret matches, then
   accept the updated registration; otherwise, reject it.
 * Applications should periodically validate their connections --
   i.e. issue an API call for "System.get" to ensure that the
   connection corresponds to an active Civi installation running
   a compatible version.
 * Applications should be deployed on HTTPS to provide additional
   security (e.g. forward-secrecy). However, this could impact
   compatibility/reach, and the protocol encrypts all messages
   regardless, so HTTP may still be used.
