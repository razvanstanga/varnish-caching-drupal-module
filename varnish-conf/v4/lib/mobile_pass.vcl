# mobile_pass.vcl -- Mobile pass-through support for Varnish

# This simply bypasses the cache for anything that looks like a mobile
# (or tablet) device.

sub vcl_recv {
    # General User-Agent blacklist (anything that remotely looks like a mobile device)
    if (req.http.User-Agent ~ "(?i)ipod|android|blackberry|phone|mobile|kindle|silk|fennec|tablet|webos|palm|windows ce|nokia|philips|samsung|sanyo|sony|panasonic|ericsson|alcatel|series60|series40|opera mini|opera mobi|au-mic|audiovox|avantgo|blazer|danger|docomo|epoc|ericy|i-mode|ipaq|midp-|mot-|netfront|nitro|pocket|portalmmm|rover|sie-|symbian|cldc-|j2me|up\.browser|up\.link|vodafone|wap1\.|wap2\.") {
        return(pass);
    }
}
