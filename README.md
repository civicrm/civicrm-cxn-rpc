Civi\Cxn\Rpc v0.1
-----------------

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

When creating a new agent, one may should use one of these four
helper classes:

 * When a site registers with an application, the site uses
   RegistrationClient, and the app uses RegistrationServer.
 * When an application sends an API call to the site, the
   app uses ApiClient, and the site uses ApiServer.

Policy Recommendations
----------------------

 * Applications should accept new registrations. Applications should
   reject existing registrations unless the shared-secret is
   identical.
 * Applications should not make assumptions about the version of
   CiviCRM used by any given site. Rather, they should periodically
   call "System.get" API to determine the configuration.
