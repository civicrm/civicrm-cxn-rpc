Civi\Cxn\Rpc v0.1
-----------------

Civi\Cxn\Rpc implements are an RPC mechanism based X.509 and JSON. 
Generally, it is based on a assymetric business relationship between two
groups:

 * "Sites" are online properties owned by end-user organizations. They
   represent an organization's canonical data-store.  There are many sites,
   and sites may self-register with an HTTP-callback validation protocol.
   In the tests and comments, we will refer to an example site
   called "SaveTheWhales.org".
 * "Applications" are online properties with value-added services. They
   supplement the sites.  There are only a few applications, and the
   registration process is closely managed. In the tests and comments,
   we will refer to an example service called "AddressCleanup.com".

Certificates
------------

In v0.1, the certificates for sites and applications follow these
constraints:

 * The DN is formed as "CN=callbackUrl, O=uniqueId"
 * The extendedKeyUsage for a site is marked ONLY as "clientAuth".
 * The extendedKeyUsage for an application is marked ONLY as "serverAuth".

Base Classes
------------

When creating a new agent, one may should use one of these four
helper classes:

 * For connecting from an application to a site, use AppClient and SiteServer.
 * For connecting from a site to an application, use SiteClient and AppServer.
