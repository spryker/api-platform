<?php

/**
 * Copyright © 2016-present Spryker Systems GmbH. All rights reserved.
 * Use of this software requires acceptance of the Evaluation License Agreement. See LICENSE file.
 */

declare(strict_types=1);

namespace SprykerTest\ApiPlatform\Test;

use CXml\Model\Credential;
use CXml\Model\CXml;
use CXml\Model\Header;
use CXml\Model\Party;
use CXml\Model\PayloadIdentity;
use CXml\Model\Request\ProfileRequest;
use CXml\Model\Request\Request;
use DateTime;
use Spryker\ApiPlatform\Serializer\Encoder\CXmlEncoder;
use SprykerTest\ApiPlatform\ApiUnitTester;
use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

/**
 * Auto-generated group annotations
 *
 * @group SprykerTest
 * @group ApiPlatform
 * @group Test
 * @group CXmlEncoderTest
 * Add your own group annotations below this line
 */
class CXmlEncoderTest extends AbstractApiTestCase
{
    protected ApiUnitTester $tester;

    protected CXmlEncoder $encoder;

    protected function _before(): void
    {
        $this->encoder = $this->getContainer()->get('serializer');
    }

    public function testEncodesCXmlObjectSuccessfully(): void
    {
        // Arrange
        $cxml = $this->createSampleCXml();

        // Act
        $result = $this->encoder->encode($cxml, 'xml');

        // Assert
        $this->assertIsString($result);
        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $result);
        $this->assertStringContainsString('<!DOCTYPE cXML SYSTEM', $result);
        $this->assertStringContainsString('<cXML', $result);
        $this->assertStringContainsString('</cXML>', $result);
    }

    public function testEncodeThrowsExceptionWhenDataIsNotCXmlObject(): void
    {
        // Arrange
        $data = ['key' => 'value'];

        // Assert
        $this->expectException(NotEncodableValueException::class);
        $this->expectExceptionMessage('The cXML encoder requires data to be an instance of');

        // Act
        $this->encoder->encode($data, 'xml');
    }

    public function testDecodesCXmlStringSuccessfully(): void
    {
        // Arrange
        $cxmlString = $this->getSampleCXmlString();

        // Act
        $result = $this->encoder->decode($cxmlString, 'xml');

        // Assert
        $this->assertInstanceOf(CXml::class, $result);
        $this->assertInstanceOf(Header::class, $result->header);
    }

    public function testDecodeThrowsExceptionForInvalidCXml(): void
    {
        // Arrange
        $invalidXml = '<invalid>xml</invalid>';

        // Assert
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('An error occurred while decoding cXML data');

        // Act
        $this->encoder->decode($invalidXml, 'cxml');
    }

    public function testDecodeThrowsExceptionForEmptyString(): void
    {
        // Arrange
        $emptyString = '';

        // Assert
        $this->expectException(UnexpectedValueException::class);

        // Act
        $this->encoder->decode($emptyString, 'xml');
    }

    public function testEncodedDataCanBeDecoded(): void
    {
        // Arrange
        $originalCxml = $this->createSampleCXml();

        // Act
        $encoded = $this->encoder->encode($originalCxml, 'xml');
        $decoded = $this->encoder->decode($encoded, 'xml');

        // Assert
        $this->assertInstanceOf(CXml::class, $decoded);
        $this->assertEquals($originalCxml->payloadId, $decoded->payloadId);
    }

    protected function createSampleCXml(): CXml
    {
        $payloadIdentity = new PayloadIdentity(
            'test-payload-' . uniqid(),
            new DateTime('2024-01-01 12:00:00'),
        );

        $fromCredential = new Credential('buyer-domain', 'buyer-identity');
        $from = new Party($fromCredential);

        $toCredential = new Credential('supplier-domain', 'supplier-identity');
        $to = new Party($toCredential);

        $senderCredential = new Credential('sender-domain', 'sender-shared-secret');
        $sender = new Party($senderCredential);

        $header = new Header($from, $to, $sender);

        $request = new Request(new ProfileRequest());

        return CXml::forRequest($payloadIdentity, $request, $header);
    }

    protected function getSampleCXmlString(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?><!DOCTYPE cXML SYSTEM "http://xml.cxml.org/schemas/cXML/1.2.063/cXML.dtd">
<cXML version="1.2.063" payloadID="test-payload-123" timestamp="2024-01-01T12:00:00+00:00">
    <Header>
        <From>
            <Credential domain="buyer-domain">
                <Identity>buyer-identity</Identity>
            </Credential>
        </From>
        <To>
            <Credential domain="supplier-domain">
                <Identity>supplier-identity</Identity>
            </Credential>
        </To>
        <Sender>
            <Credential domain="sender-domain">
                <SharedSecret>sender-shared-secret</SharedSecret>
            </Credential>
        </Sender>
    </Header>
    <Request>
        <ProfileRequest/>
    </Request>
</cXML>
XML;
    }
}
