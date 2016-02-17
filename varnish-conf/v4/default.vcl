vcl 4.0;

backend default {
    .host = "192.168.0.2";
    .port = "80";
}

import std;

include "lib/xforward.vcl";
include "lib/cloudflare.vcl";
include "lib/purge.vcl";
include "lib/bigfiles.vcl";
include "lib/static.vcl";

acl cloudflare {
    # set this ip to your Railgun IP (if applicable)
    # "1.2.3.4";
}

acl purge {
    "localhost";
    "127.0.0.1";
    #"192.168.0.2";
}

# Pick just one of the following:
# (or don't use either of these if your application is "adaptive")
# include "lib/mobile_cache.vcl";
# include "lib/mobile_pass.vcl";

### Drupal-specific config ###
sub vcl_recv {
    set req.http.hash = "";
    # pipe on weird http methods
    if (req.method !~ "^GET|HEAD|PUT|POST|TRACE|OPTIONS|DELETE$") {
        return(pipe);
    }

    # redirect yourdomain.com to www.yourdomain.com
    if (req.http.host ~ "^yourdomain\.com$") {
        set req.http.X-VC-Redirect = "http://www.yourdomain.com" + req.url;
        return (synth(750, "Moved permanently"));
    }

    # if you use a subdomain for admin section, do not cache it
    if (req.http.host ~ "admin.yourdomain.com") {
        return(pass);
    }

    ### Check for reasons to bypass the cache!
    # never cache anything except GET/HEAD
    if (req.method != "GET" && req.method != "HEAD") {
        return(pass);
    }

    # don't cache logged-in users. you can set users `logged in cookie` name in settings
    if (req.http.Cookie ~ "c005492c65") {
        set req.http.X-VC-GotSession = "true";
        return(pass);
    }

    # don't cache ajax requests
    if (req.http.X-Requested-With == "XMLHttpRequest") {
        return(pass);
    }

    # don't cache these special pages
    if (req.url ~ "status\.php|update\.php|admin|admin/.*|flag/.*|.*/ajax/.*|.*/ahah/.*") {
        set req.http.X-VC-GotUrl = "true";
        return(pass);
    }

    ### looks like we might actually cache it!

    # strip query parameters from all urls (so they cache as a single object)
    # be carefull using this option
    #if (req.url ~ "\?.*") {
    #    set req.url = regsub(req.url, "\?.*", "");
    #}

    # Remove has_js, Google Analytics __*
    set req.http.Cookie = regsuball(req.http.Cookie, "(^|;\s*)(__[a-z]+|has_js)=[^;]*", "");
    set req.http.Cookie = regsub(req.http.Cookie, "^;\s*", "");
    if (req.http.Cookie ~ "^\s*$") {
        unset req.http.Cookie;
    }

    return(hash);
}

sub vcl_hash {
    set req.http.hash = req.url;
    if (req.http.host) {
        set req.http.hash = req.http.hash + "#" + req.http.host;
    } else {
        set req.http.hash = req.http.hash + "#" + server.ip;
    }
    # Add the browser cookie only if a Drupal cookie found. Not needed anymore, left here as example
    #if (req.http.Cookie ~ "SESS") {
    #    hash_data(req.http.Cookie);
    #    set req.http.hash = req.http.hash + "#" + req.http.Cookie;
    #}
}

sub vcl_backend_response {
    # make sure grace is at least 2 minutes
    if (beresp.grace < 2m) {
        set beresp.grace = 2m;
    }

    # overwrite ttl with X-VC-TTL
    if (beresp.http.X-VC-TTL) {
        set beresp.ttl = std.duration(beresp.http.X-VC-TTL + "s", 0s);
    }

    # catch obvious reasons we can't cache
    if (beresp.http.Set-Cookie) {
        set beresp.ttl = 0s;
    }

    # You don't wish to cache content for logged in users
    if (bereq.http.X-VC-GotSession ~ "true" || beresp.http.X-VC-GotSession ~ "true") {
        set beresp.http.X-VC-Cacheable = "NO:Got Session";
        set beresp.uncacheable = true;
        set beresp.ttl = 120s;

    # Varnish determined the object was not cacheable
    } else if (beresp.ttl <= 0s) {
        set beresp.http.X-VC-Cacheable = "NO:Not Cacheable";
        set beresp.uncacheable = true;
        set beresp.ttl = 120s;

    # You are respecting the Cache-Control=private header from the backend
    } else if (beresp.http.Cache-Control ~ "private") {
        set beresp.http.X-VC-Cacheable = "NO:Cache-Control=private";
        set beresp.uncacheable = true;
        set beresp.ttl = 120s;

    # You are respecting the X-VC-Enabled=true header from the backend
    } else if (beresp.http.X-VC-Enabled ~ "true") {
        set beresp.http.X-VC-Cacheable = "YES";

    # Do not cache object
    } else if (beresp.http.X-VC-Enabled ~ "false") {
        set beresp.http.X-VC-Cacheable = "NO:Disabled";
        set beresp.ttl = 0s;
    }

    # Avoid caching error responses
    if (beresp.status == 404 || beresp.status >= 500) {
        set beresp.ttl   = 0s;
        set beresp.grace = 15s;
    }

    # Deliver the content
    return(deliver);
}

sub vcl_synth {
    if (resp.status == 750) {
        set resp.http.Location = req.http.X-VC-Redirect;
        set resp.status = 302;
        return(deliver);
    }
}
