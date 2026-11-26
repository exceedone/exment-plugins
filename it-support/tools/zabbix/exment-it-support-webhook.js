// Exment IT Support: the script of a Zabbix webhook media type (Zabbix 6.0 or later).
// Sends a problem and its recovery to /support/intake/alert of Exment IT Support,
// signed when a secret is given, and returns the ticket number as the tag "its_ticket".
//
// Parameters of the media type (name = value):
//   its_url        = https://your-exment/support/intake/alert
//   its_key        = the key of the sender (Zabbix) made in IT Support
//   its_secret     = the signing secret of the sender (leave empty when no signature is asked)
//   event_value    = {EVENT.VALUE}
//   event_id       = {EVENT.ID}
//   trigger_id     = {TRIGGER.ID}
//   subject        = {ALERT.SUBJECT}
//   message        = {ALERT.MESSAGE}
//   host           = {HOST.HOST}
//   host_ip        = {HOST.IP}
//   event_severity = {EVENT.SEVERITY}
//   event_nseverity = {EVENT.NSEVERITY}
//   event_tags     = {EVENT.TAGS}
//   event_opdata   = {EVENT.OPDATA}
//   event_url      = {$ZABBIX.URL}/tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}
//   customer       = (optional) the customer code in IT Support
// "Process tags" on, to see the ticket number on the problem.
var ItsWebhook = {
    fields: ['event_value', 'event_id', 'trigger_id', 'subject', 'message', 'host', 'host_ip',
        'event_severity', 'event_nseverity', 'event_tags', 'event_opdata', 'event_url', 'customer'],

    unresolved: function (v) {
        // a macro Zabbix could not fill stays as "{...}"
        return typeof v !== 'string' || /^\{[A-Z0-9_.$]+\}$/.test(v) ? '' : v;
    },

    send: function (params) {
        if (!params.its_url || !params.its_key) {
            throw 'its_url and its_key are required';
        }
        var body = {};
        ItsWebhook.fields.forEach(function (name) {
            body[name] = ItsWebhook.unresolved(params[name]);
        });
        var json = JSON.stringify(body);

        var request = new HttpRequest();
        request.addHeader('Content-Type: application/json');
        request.addHeader('X-ITS-Key: ' + params.its_key);
        if (params.its_secret) {
            var ts = Math.floor(Date.now() / 1000).toString();
            request.addHeader('X-ITS-Timestamp: ' + ts);
            request.addHeader('X-ITS-Signature: sha256=' + hmac('sha256', params.its_secret, ts + '.' + json));
        }
        if (params.HTTPProxy) {
            request.setProxy(params.HTTPProxy);
        }
        var response = request.post(params.its_url, json);
        var status = request.getStatus();
        Zabbix.log(4, '[Exment IT Support webhook] status ' + status + ': ' + response);
        if (status < 200 || status >= 300) {
            throw 'Exment IT Support answered ' + status + ': ' + response;
        }
        var answer = {};
        try {
            answer = JSON.parse(response);
        } catch (e) {
            answer = {};
        }
        var tags = {};
        if (answer.ticket_no) {
            tags.its_ticket = answer.ticket_no;
        }

        return JSON.stringify({tags: tags});
    }
};

try {
    return ItsWebhook.send(JSON.parse(value));
} catch (error) {
    Zabbix.log(3, '[Exment IT Support webhook] failed: ' + error);
    throw 'Sending failed: ' + error;
}
