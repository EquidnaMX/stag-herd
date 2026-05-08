<?php

namespace Equidna\StagHerd\Tests\Unit\Support;

use Equidna\StagHerd\Support\WebhookVerifier;
use Equidna\StagHerd\Tests\TestCase;
use Illuminate\Http\Request;

class WebhookVerifierTest extends TestCase
{
    private const PRIVATE_KEY = <<<'PEM'
-----BEGIN RSA PRIVATE KEY-----
MIIEowIBAAKCAQEAotNHAIZOPiAg4bRIWEXl/stUGtgDWFxVtvUkh9YRi6QkBDvr
4S9Z/TbgMWbY9WEortaBExhVeR2V40npXfdyDGDH6JeOxpf4Irv8DBreL8b/OPAm
HbArEv3psFNrsQppEcNKSiR4v7medi4STGQbr7lI4nJQq6geUKgHKyPKv2GZkNfW
RarjxP00TF5q2IBTwU9mz4dXVwXQBWIulPoXUQA4t//lWhW3kqebmU/+j3++VDji
F7wqG93WDGzInoOIooSjGl0nmftrmnnfV69wzt5bgqiqABDMJLtwiTq1G3u4+aTw
qLPFb0P//TzEaxPQm8MT1KXtqOJ3xmnaV1gv6QIDAQABAoIBAEWa7A9dWrVUJrpV
x1d1Cg0R/rI7BbMQRdQgl6055eY8FOl6dCufgmisvMphnP3IuwuCx0rSbDeKXjj7
r7drwGVqOgtEPtTGwlyW3/iMw87I0vIUNzcZyFAmG3A1OSRFvwTI50kqrjPHteXv
bAMcCHnmSzICjFnVVKBGNEpM0q9c6yM48IN5UIAtFegF6dbkmhG5CCUti3KDnzD0
JPYhpDvwpTaPA6zefK8BsHoaniiCDcyICSFBh8rZeLS+8lVE0Es8roYKUkfGNpSh
8AIq+2wTfWAIVIl9W5j5eRt/HFImP8g3CKfuZlKukZVNKmkMeBHAjWP39TbMbujG
m/KvEwECgYEAz9jRUu0v5ppjZIzqEJEp77vTpI37N8qFebQfoNqqqarj/JeWs7sf
HG4/Wa+8Wr72BGCqyfPoBhEoZd5Z4aXJJMP/A5inzSvZ9nyuIndGhhJRBZeALyy0
3cYeTodjTj6OzKEVRsTrIS3K0j0zTpOyxkmkRa0l1CPtZcUSn2rxNMUCgYEAyIxG
E64QqeQB2hTf8L/373DEejHaCxR6U8pI7rZNbtcwi+MdEz+eprcQGi8RTFttmRGe
kDu1WwwDVNtA5Ce9aNbydFonKzAQOH1VkGTEwQEhf43UOb/ALejf7Z8UcWDGvy76
PK+r2xJZ/6k9qDlq+MrzZB3NW1JbNqeFreZOqNUCgYEAnJIvNXH5iJS61O8WD77a
wX5Tc32FRkTogYK/5LN0pnVWY1xqKSCH0in2zQAGOrHpr+iGH7X+Djv0m7CBMutp
d6mxvCrOFU+4VOcdYldJqAu6PyUeaesaoInzIKL6muGjPuEFnxcOLSNKy09RDBtV
K+wjtF03xvP9jJGFctPjuiUCgYA1ZGMjyivVj0aO8Q/N4M35iWRFxA/w8zq+JBTW
uOJUqCXcmFKdVUq/x+0Zi35nfc/n+LDmZX8bBk+8v8K++3WJ+/AA2h+jd+BZqcSl
9K4NVGe+tdvSpCJeFqnHcZrXtJQ1QiSNE5gFcxVS45PuvZqlMiMqfGS3820lV+RX
MAGxIQKBgADqy3xzR8WMboqx+QoL/7Q8TQiv+ODwpt+WB1uCXpPRtyaZF8cfaCwG
RFv1NWeQu9fiCqfhBCmhrxPd14oK4g+QVrRJd+vZbHZijHe/+X0d09uyrgfNqVIo
zkIi+eac+uK+ZGL6F7M7WkL++CtLqXiDvQFKx1baHO70fhdWvS7z
-----END RSA PRIVATE KEY-----
PEM;

    private const PUBLIC_KEY = <<<'PEM'
-----BEGIN PUBLIC KEY-----
MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEAotNHAIZOPiAg4bRIWEXl
/stUGtgDWFxVtvUkh9YRi6QkBDvr4S9Z/TbgMWbY9WEortaBExhVeR2V40npXfdy
DGDH6JeOxpf4Irv8DBreL8b/OPAmHbArEv3psFNrsQppEcNKSiR4v7medi4STGQb
r7lI4nJQq6geUKgHKyPKv2GZkNfWRarjxP00TF5q2IBTwU9mz4dXVwXQBWIulPoX
UQA4t//lWhW3kqebmU/+j3++VDjiF7wqG93WDGzInoOIooSjGl0nmftrmnnfV69w
zt5bgqiqABDMJLtwiTq1G3u4+aTwqLPFb0P//TzEaxPQm8MT1KXtqOJ3xmnaV1gv
6QIDAQAB
-----END PUBLIC KEY-----
PEM;

    public function test_conekta_verifies_valid_rsa_digest(): void
    {
        $payload = '{"id":"evt_123","type":"charge.paid"}';

        openssl_sign($payload, $signature, self::PRIVATE_KEY, OPENSSL_ALGO_SHA256);

        config(['stag-herd.conekta.webhook_public_key' => self::PUBLIC_KEY]);

        $request = Request::create('/webhook/conekta', 'POST', [], [], [], [
            'HTTP_DIGEST' => base64_encode($signature),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $result = WebhookVerifier::verifyConektaSignature($request);

        $this->assertTrue($result['valid']);
        $this->assertSame('evt_123', $result['eventId']);
    }

    public function test_conekta_rejects_hmac_digest(): void
    {
        $payload = '{"id":"evt_123","type":"charge.paid"}';

        config(['stag-herd.conekta.webhook_public_key' => self::PUBLIC_KEY]);

        $request = Request::create('/webhook/conekta', 'POST', [], [], [], [
            'HTTP_DIGEST' => base64_encode(hash_hmac('sha256', $payload, 'secret', true)),
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        $result = WebhookVerifier::verifyConektaSignature($request);

        $this->assertFalse($result['valid']);
        $this->assertSame('Digest mismatch', $result['reason']);
    }
}
