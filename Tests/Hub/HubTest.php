<?php

/*
 * This file is part of the Hearsay PubSubHubbub bundle.
 *
 * The Hearsay PubSubHubbub bundle is free software: you can redistribute it
 * and/or modify it under the terms of the GNU Lesser General Public License
 * as published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * The Hearsay PubSubHubbub bundle is distributed in the hope that it will be
 * useful, but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with the Hearsay PubSubHubbub bundle.  If not, see
 * <http://www.gnu.org/licenses/>.
 */

namespace Hearsay\PubSubHubbubBundle\Tests\Hub;

use Hearsay\PubSubHubbubBundle\Hub\Hub;
use Hearsay\PubSubHubbubBundle\Web\Curl;

/**
 * Unit tests for hub connections.
 * @package HearsayPubSubHubbubBundle
 * @subpackage Tests
 * @author Kevin Montag <kevin@hearsay.it>
 */
class HubTest extends \PHPUnit_Framework_TestCase {

    private function getMockCurl() {
        return $this->getMock('Hearsay\PubSubHubbubBundle\Web\Curl', array('fetch', '__destruct'));
    }

    /**
     * Get a constraint matcher object for a equality with a cURL handle object,
     * regardless of changes made to the object after the constraint is created.
     * @param Curl $curl The request object.
     * @return \PHPUnit_Framework_Constraint_Attribute The constraint matcher.
     */
    private function equalsCurl(Curl $curl) {
        return $this->attributeEqualTo('ch', $this->readAttribute($curl, 'ch'));
    }

    /**
     * Make sure the hub passes any options provided during requests down to
     * its components, using component-specified defaults for options which are
     * not provided.
     * @covers Hearsay\PubSubHubbubBundle\Hub\Hub
     */
    public function testOptionsProvidedToComponents() {
        $curl = $this->getMockCurl();
        $curlFactory = $this->getMock('Hearsay\PubSubHubbubBundle\Web\CurlFactory');
        $curlFactory
                ->expects($this->once())
                ->method('createCurl')
                ->with('http://test.url.com')
                ->will($this->returnValue($curl));

        $component1 = $this->getMock('Hearsay\PubSubHubbubBundle\Hub\HubComponentInterface');
        $component2 = $this->getMock('Hearsay\PubSubHubbubBundle\Hub\HubComponentInterface');
        $components = array($component1, $component2);

        $hub = new Hub('http://test.url.com', $components, $curlFactory);

        $component1
                ->expects($this->any())
                ->method('getOptions')
                ->with($hub, 'test')
                ->will($this->returnValue(array(
                            'opt' => 'def',
                            'opt2' => 'def2',
                        )));

        $component1
                ->expects($this->once())
                ->method('getParameters')
                ->with($hub, 'test', array(
                    'opt' => 'def',
                    'opt2' => 'nonDef',
                ))
                ->will($this->returnValue(array()));
        $component1
                ->expects($this->once())
                ->method('modifyRequest')
                ->with($hub, 'test', array(
                    'opt' => 'def',
                    'opt2' => 'nonDef',
                        ), $this->equalsCurl($curl));

        $component2
                ->expects($this->any())
                ->method('getOptions')
                ->with($hub, 'test')
                ->will($this->returnValue(array(
                            'option' => 'default',
                            'option2' => 'default2',
                        )));
        $component2
                ->expects($this->once())
                ->method('getParameters')
                ->with($hub, 'test', array(
                    'option' => 'nonDefault',
                    'option2' => 'default2',
                ))
                ->will($this->returnValue(array()));
        $component2
                ->expects($this->once())
                ->method('modifyRequest')
                ->with($hub, 'test', array(
                    'option' => 'nonDefault',
                    'option2' => 'default2',
                        ), $this->equalsCurl($curl));

        $hub->makeRequest('test', array(
            'opt2' => 'nonDef',
            'option' => 'nonDefault',
        ));
    }

    /**
     * Make sure the component-provided POST fields are used when hub requests
     * are made.
     * @covers Hearsay\PubSubHubbubBundle\Hub\Hub
     */
    public function testComponentPostFieldsUsed() {
        $curl = $this->getMockCurl();
        $curlFactory = $this->getMock('Hearsay\PubSubHubbubBundle\Web\CurlFactory');
        $curlFactory
                ->expects($this->once())
                ->method('createCurl')
                ->with('http://test.url.com')
                ->will($this->returnValue($curl));

        $component1 = $this->getMock('Hearsay\PubSubHubbubBundle\Hub\HubComponentInterface');
        $component2 = $this->getMock('Hearsay\PubSubHubbubBundle\Hub\HubComponentInterface');
        $components = array($component1, $component2);

        $hub = new Hub('http://test.url.com', $components, $curlFactory);

        $component1
                ->expects($this->any())
                ->method('getOptions')
                ->will($this->returnValue(array()));
        $component2
                ->expects($this->any())
                ->method('getOptions')
                ->will($this->returnValue(array()));

        $component1
                ->expects($this->any())
                ->method('getParameters')
                ->with($hub, 'test', array())
                ->will($this->returnValue(array(
                            'first' => 'First value',
                        )));
        $component2
                ->expects($this->any())
                ->method('getParameters')
                ->with($hub, 'test', array())
                ->will($this->returnValue(array(
                            'second' => 'Second value',
                            'third' => 'Third value',
                        )));

        // Make sure the parameters are present when the request is executed
        $that = $this;
        $curl
                ->expects($this->once())
                ->method('fetch')
                ->will($this->returnCallback(function() use ($that, $curl) {
                                    $that->assertEquals(4, \count($curl->postFields));
                                    $that->assertEquals('First value', $curl->postFields['first']);
                                    $that->assertEquals('Second value', $curl->postFields['second']);
                                    $that->assertEquals('Third value', $curl->postFields['third']);
                                    $that->assertEquals('test', $curl->postFields['hub.mode']);
                                }));

        $hub->makeRequest('test');
    }

}
