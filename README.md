Civi\Cxn\Rpc
------------

Civi\Cxn\Rpc implements are an RPC mechanism based X.509 and JSON.
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

 * Applications should accept new registrations. Applications should
   reject existing registrations unless the shared-secret is
   identical.
 * Applications should not make assumptions about the version of
   CiviCRM used by any given site. Rather, they should periodically
   call "System.get" API to determine the configuration.
