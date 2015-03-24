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
   the application (using the app's RSA public-key).
 * Applications may send API calls to sites. In accordance with Civi
   API, these are framed as entity+action+params. All API calls
   are encrypted with the shared secret (AES-256).

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
 * Services should be deployed on HTTPS to provide additional
   security (e.g. forward-secrecy). However, this could impact
   compatibility/reach, and the protocol encrypts all messages
   regardless, so HTTP may still be used.
