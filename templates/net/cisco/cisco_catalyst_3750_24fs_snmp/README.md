
# Cisco Catalyst 3750V2-24FS SNMP

## Overview

For Zabbix version: 5.0 and higher  
![Product picture](images/pic.png?raw=true)
> Courtesy of Cisco Systems, Inc. Unauthorized use not permitted.

The Cisco Catalyst 3750 Series switches are a premier line of enterprise-class, stackable, multilayer switches that provide high availability, security, and quality of service (QoS) to enhance the operation of the network. Its innovative unified stack management raises the bar in stack management, redundancy, and failover.

https://www.cisco.com/c/en/us/support/switches/catalyst-3750v2-24fs-switch/model.html

## Setup

> See [Zabbix template operation](https://www.zabbix.com/documentation/5.0/manual/config/templates_out_of_the_box/network_devices) for basic instructions.

Refer to the vendor documentation.

## Zabbix configuration

No specific Zabbix configuration is required.

### Macros used

|Name|Description|Default|
|----|-----------|-------|
|{$CPU.UTIL.CRIT} |<p>-</p> |`90` |
|{$ICMP_LOSS_WARN} |<p>-</p> |`20` |
|{$ICMP_RESPONSE_TIME_WARN} |<p>-</p> |`0.15` |
|{$IF.ERRORS.WARN} |<p>-</p> |`2` |
|{$IF.UTIL.MAX} |<p>-</p> |`90` |
|{$IFCONTROL} |<p>-</p> |`1` |
|{$MEMORY.UTIL.MAX} |<p>-</p> |`90` |
|{$NET.IF.IFADMINSTATUS.MATCHES} |<p>-</p> |`^.*` |
|{$NET.IF.IFADMINSTATUS.NOT_MATCHES} |<p>Ignore down(2) administrative status</p> |`^2$` |
|{$NET.IF.IFALIAS.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFALIAS.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFDESCR.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFDESCR.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$NET.IF.IFNAME.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFNAME.NOT_MATCHES} |<p>Filter out loopbacks, nulls, docker veth links and docker0 bridge by default</p> |`(^Software Loopback Interface|^NULL[0-9.]*$|^[Ll]o[0-9.]*$|^[Ss]ystem$|^Nu[0-9.]*$|^veth[0-9a-z]+$|docker[0-9]+|br-[a-z0-9]{12})` |
|{$NET.IF.IFOPERSTATUS.MATCHES} |<p>-</p> |`^.*$` |
|{$NET.IF.IFOPERSTATUS.NOT_MATCHES} |<p>Ignore notPresent(6)</p> |`^6$` |
|{$NET.IF.IFTYPE.MATCHES} |<p>-</p> |`.*` |
|{$NET.IF.IFTYPE.NOT_MATCHES} |<p>-</p> |`CHANGE_IF_NEEDED` |
|{$SNMP.TIMEOUT} |<p>-</p> |`5m` |
|{$TEMP_CRIT_LOW} |<p>-</p> |`5` |
|{$TEMP_CRIT} |<p>-</p> |`60` |
|{$TEMP_WARN} |<p>-</p> |`50` |

## Template links

There are no template links in this template.

## Discovery rules

|Name|Description|Type|Key and additional info|
|----|-----------|----|----|
|CPU discovery |<p>If your IOS device has several CPUs, you must use CISCO-PROCESS-MIB and its object cpmCPUTotal5minRev from the table called cpmCPUTotalTable ,</p><p>indexed with cpmCPUTotalIndex .</p><p>This table allows CISCO-PROCESS-MIB to keep CPU statistics for different physical entities in the router,</p><p>like different CPU chips, group of CPUs, or CPUs in different modules/cards.</p><p>In case of a single CPU, cpmCPUTotalTable has only one entry.</p> |SNMP |cpu.discovery |
|Entity Serial Numbers discovery |<p>-</p> |SNMP |entity_sn.discovery<p>**Filter**:</p>AND <p>- B: {#ENT_SN} MATCHES_REGEX `.+`</p><p>- A: {#ENT_CLASS} MATCHES_REGEX `[^3]`</p> |
|FAN discovery |<p>The table of fan status maintained by the environmental monitor.</p> |SNMP |fan.discovery |
|Memory discovery |<p>Discovery of ciscoMemoryPoolTable, a table of memory pool monitoring entries.</p><p>http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |memory.discovery |
|Network interfaces discovery |<p>Discovering interfaces from IF-MIB.</p> |SNMP |net.if.discovery<p>**Filter**:</p>AND <p>- A: {#IFADMINSTATUS} MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.MATCHES}`</p><p>- B: {#IFADMINSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFADMINSTATUS.NOT_MATCHES}`</p><p>- I: {#IFOPERSTATUS} MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.MATCHES}`</p><p>- J: {#IFOPERSTATUS} NOT_MATCHES_REGEX `{$NET.IF.IFOPERSTATUS.NOT_MATCHES}`</p><p>- G: {#IFNAME} MATCHES_REGEX `{$NET.IF.IFNAME.MATCHES}`</p><p>- H: {#IFNAME} NOT_MATCHES_REGEX `{$NET.IF.IFNAME.NOT_MATCHES}`</p><p>- E: {#IFDESCR} MATCHES_REGEX `{$NET.IF.IFDESCR.MATCHES}`</p><p>- F: {#IFDESCR} NOT_MATCHES_REGEX `{$NET.IF.IFDESCR.NOT_MATCHES}`</p><p>- C: {#IFALIAS} MATCHES_REGEX `{$NET.IF.IFALIAS.MATCHES}`</p><p>- D: {#IFALIAS} NOT_MATCHES_REGEX `{$NET.IF.IFALIAS.NOT_MATCHES}`</p><p>- K: {#IFTYPE} MATCHES_REGEX `{$NET.IF.IFTYPE.MATCHES}`</p><p>- L: {#IFTYPE} NOT_MATCHES_REGEX `{$NET.IF.IFTYPE.NOT_MATCHES}`</p> |
|EtherLike discovery |<p>Discovering interfaces from IF-MIB and EtherLike-MIB. Interfaces with up(1) Operational Status are discovered.</p> |SNMP |net.if.duplex.discovery<p>**Filter**:</p>AND <p>- A: {#IFOPERSTATUS} MATCHES_REGEX `1`</p><p>- B: {#SNMPVALUE} MATCHES_REGEX `(2|3)`</p> |
|PSU discovery |<p>The table of power supply status maintained by the environmental monitor card.</p> |SNMP |psu.discovery |
|Temperature discovery |<p>Discovery of ciscoEnvMonTemperatureTable (ciscoEnvMonTemperatureDescr), a table of ambient temperature status</p><p>maintained by the environmental monitor.</p> |SNMP |temperature.discovery |

## Items collected

|Group|Name|Description|Type|Key and additional info|
|-----|----|-----------|----|---------------------|
|CPU |#{#SNMPINDEX}: CPU utilization |<p>MIB: CISCO-PROCESS-MIB</p><p>Object name: cpmCPUTotal5minRev</p><p>The cpmCPUTotal5minRev MIB object provides a more accurate view of the performance of the router over time than the MIB objects cpmCPUTotal1minRev and cpmCPUTotal5secRev . These MIB objects are not accurate because they look at CPU at one minute and five second intervals, respectively. These MIBs enable you to monitor the trends and plan the capacity of your network. The recommended baseline rising threshold for cpmCPUTotal5minRev is 90 percent. Depending on the platform, some routers that run at 90 percent, for example, 2500s, can exhibit performance degradation versus a high-end router, for example, the 7500 series, which can operate fine.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15215-collect-cpu-util-snmp.html</p> |SNMP |system.cpu.util[{#SNMPINDEX}] |
|Fans |{#SNMPVALUE}: Fan status |<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonFanState</p> |SNMP |sensor.fan.status[{#SNMPINDEX}] |
|General |SNMP traps (fallback) |<p>Item is used to collect all SNMP traps unmatched by other snmptrap items</p> |SNMP_TRAP |snmptrap.fallback |
|General |System contact details |<p>MIB: SNMPv2-MIB</p><p>The textual identification of the contact person for this managed node, together with information on how to contact this person.  If no contact information is known, the value is the zero-length string.</p> |SNMP |system.contact<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |System description |<p>MIB: SNMPv2-MIB</p><p>A textual description of the entity. This value should</p><p>include the full name and version identification of the system's hardware type, software operating-system, and</p><p>networking software.</p> |SNMP |system.descr<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|General |System location |<p>MIB: SNMPv2-MIB</p><p>The physical location of this node (e.g., `telephone closet, 3rd floor').  If the location is unknown, the value is the zero-length string.</p> |SNMP |system.location<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|General |System name |<p>MIB: SNMPv2-MIB</p><p>An administratively-assigned name for this managed node.By convention, this is the node's fully-qualified domain name.  If the name is unknown, the value is the zero-length string.</p> |SNMP |system.name<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|General |System object ID |<p>MIB: SNMPv2-MIB</p><p>The vendor's authoritative identification of the network management subsystem contained in the entity.  This value is allocated within the SMI enterprises subtree (1.3.6.1.4.1) and provides an easy and unambiguous means for determining`what kind of box' is being managed.  For example, if vendor`Flintstones, Inc.' was assigned the subtree1.3.6.1.4.1.4242, it could assign the identifier 1.3.6.1.4.1.4242.1.1 to its `Fred Router'.</p> |SNMP |system.objectid<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Inventory |Hardware model name |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.model<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Hardware serial number |<p>MIB: ENTITY-MIB</p> |SNMP |system.hw.serialnumber<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |Operating system |<p>MIB: SNMPv2-MIB</p> |SNMP |system.sw.os<p>**Preprocessing**:</p><p>- REGEX: `Version (.+), RELEASE \1`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Inventory |{#ENT_NAME}: Hardware serial number |<p>MIB: ENTITY-MIB</p><p>Object name: entPhysicalSerialNum</p> |SNMP |system.hw.serialnumber[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1d`</p> |
|Memory |{#SNMPVALUE}: Free memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Object name: ciscoMemoryPoolFree</p><p>Indicates the number of bytes from the memory pool that are currently unused on the managed device. Note that the sum of ciscoMemoryPoolUsed and ciscoMemoryPoolFree is the total amount of memory in the pool</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.free[{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Used memory |<p>MIB: CISCO-MEMORY-POOL-MIB</p><p>Object name: ciscoMemoryPoolUsed</p><p>Indicates the number of bytes from the memory pool that are currently in use by applications on the managed device.</p><p>Reference: http://www.cisco.com/c/en/us/support/docs/ip/simple-network-management-protocol-snmp/15216-contiguous-memory.html</p> |SNMP |vm.memory.used[{#SNMPINDEX}] |
|Memory |{#SNMPVALUE}: Memory utilization |<p>Memory utilization in %</p> |CALCULATED |vm.memory.util[{#SNMPINDEX}]<p>**Expression**:</p>`last("vm.memory.used[{#SNMPINDEX}]")/(last("vm.memory.free[{#SNMPINDEX}]")+last("vm.memory.used[{#SNMPINDEX}]"))*100` |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets discarded |<p>MIB: IF-MIB</p><p>The number of inbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.discards[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Inbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of inbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of inbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in.errors[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Bits received |<p>MIB: IF-MIB</p><p>The total number of octets received on the interface, including framing characters. This object is a 64-bit version of ifInOctets. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.in[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets discarded |<p>MIB: IF-MIB</p><p>The number of outbound packets which were chosen to be discarded</p><p>even though no errors had been detected to prevent their being deliverable to a higher-layer protocol.</p><p>One possible reason for discarding such a packet could be to free up buffer space.</p><p>Discontinuities in the value of this counter can occur at re-initialization of the management system,</p><p>and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.discards[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Outbound packets with errors |<p>MIB: IF-MIB</p><p>For packet-oriented interfaces, the number of outbound packets that contained errors preventing them from being deliverable to a higher-layer protocol.  For character-oriented or fixed-length interfaces, the number of outbound transmission units that contained errors preventing them from being deliverable to a higher-layer protocol. Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out.errors[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Bits sent |<p>MIB: IF-MIB</p><p>The total number of octets transmitted out of the interface, including framing characters. This object is a 64-bit version of ifOutOctets.Discontinuities in the value of this counter can occur at re-initialization of the management system, and at other times as indicated by the value of ifCounterDiscontinuityTime.</p> |SNMP |net.if.out[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- CHANGE_PER_SECOND: ``</p><p>- MULTIPLIER: `8`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Speed |<p>MIB: IF-MIB</p><p>An estimate of the interface's current bandwidth in units of 1,000,000 bits per second. If this object reports a value of `n' then the speed of the interface is somewhere in the range of `n-500,000' to`n+499,999'.  For interfaces which do not vary in bandwidth or for those where no accurate estimation can be made, this object should contain the nominal bandwidth. For a sub-layer which has no concept of bandwidth, this object should be zero.</p> |SNMP |net.if.speed[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- MULTIPLIER: `1000000`</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `1h`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Operational status |<p>MIB: IF-MIB</p><p>The current operational state of the interface.</p><p>- The testing(3) state indicates that no operational packet scan be passed</p><p>- If ifAdminStatus is down(2) then ifOperStatus should be down(2)</p><p>- If ifAdminStatus is changed to up(1) then ifOperStatus should change to up(1) if the interface is ready to transmit and receive network traffic</p><p>- It should change todormant(5) if the interface is waiting for external actions (such as a serial line waiting for an incoming connection)</p><p>- It should remain in the down(2) state if and only if there is a fault that prevents it from going to the up(1) state</p><p>- It should remain in the notPresent(6) state if the interface has missing(typically, hardware) components.</p> |SNMP |net.if.status[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Interface type |<p>MIB: IF-MIB</p><p>The type of interface.</p><p>Additional values for ifType are assigned by the Internet Assigned Numbers Authority (IANA),</p><p>through updating the syntax of the IANAifType textual convention.</p> |SNMP |net.if.type[{#SNMPINDEX}]<p>**Preprocessing**:</p><p>- DISCARD_UNCHANGED_HEARTBEAT: `6h`</p> |
|Network_interfaces |Interface {#IFNAME}({#IFALIAS}): Duplex status |<p>MIB: EtherLike-MIB</p><p>Object name: dot3StatsDuplexStatus</p><p>The current mode of operation of the MAC</p><p>entity.  'unknown' indicates that the current</p><p>duplex mode could not be determined.</p><p>Management control of the duplex mode is</p><p>accomplished through the MAU MIB.  When</p><p>an interface does not support autonegotiation,</p><p>or when autonegotiation is not enabled, the</p><p>duplex mode is controlled using</p><p>ifMauDefaultType.  When autonegotiation is</p><p>supported and enabled, duplex mode is controlled</p><p>using ifMauAutoNegAdvertisedBits.  In either</p><p>case, the currently operating duplex mode is</p><p>reflected both in this object and in ifMauType.</p><p>Note that this object provides redundant</p><p>information with ifMauType.  Normally, redundant</p><p>objects are discouraged.  However, in this</p><p>instance, it allows a management application to</p><p>determine the duplex status of an interface</p><p>without having to know every possible value of</p><p>ifMauType.  This was felt to be sufficiently</p><p>valuable to justify the redundancy.</p><p>Reference: [IEEE 802.3 Std.], 30.3.1.1.32,aDuplexStatus.</p> |SNMP |net.if.duplex[{#SNMPINDEX}] |
|Power_supply |{#SNMPVALUE}: Power supply status |<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonSupplyState</p> |SNMP |sensor.psu.status[{#SNMPINDEX}] |
|Status |ICMP ping | |SIMPLE |icmpping |
|Status |ICMP loss | |SIMPLE |icmppingloss |
|Status |ICMP response time | |SIMPLE |icmppingsec |
|Status |Uptime |<p>MIB: SNMPv2-MIB</p><p>The time (in hundredths of a second) since the network management portion of the system was last re-initialized.</p> |SNMP |system.uptime<p>**Preprocessing**:</p><p>- MULTIPLIER: `0.01`</p> |
|Status |SNMP agent availability | |INTERNAL |zabbix[host,snmp,available] |
|Temperature |{#SNMPVALUE}: Temperature status |<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonTemperatureState</p><p>The current state of the test point being instrumented.</p> |SNMP |sensor.temp.status[{#SNMPINDEX}] |
|Temperature |{#SNMPVALUE}: Temperature |<p>MIB: CISCO-ENVMON-MIB</p><p>Object name: ciscoEnvMonTemperatureValue</p><p>The current measurement of the test point being instrumented.</p> |SNMP |sensor.temp.value[{#SNMPINDEX}] |

## Triggers

|Name|Description|Expression|Severity|Dependencies and additional info|
|----|-----------|----|----|----|
|#{#SNMPINDEX}: High CPU utilization (over {$CPU.UTIL.CRIT}% for 5m) |<p>CPU utilization is too high. The system might be slow to respond.</p> |`{TEMPLATE_NAME:system.cpu.util[{#SNMPINDEX}].min(5m)}>{$CPU.UTIL.CRIT}` |WARNING | |
|{#SNMPVALUE}: Fan is in critical state |<p>Please check the fan unit</p> |`{TEMPLATE_NAME:sensor.fan.status[{#SNMPINDEX}].last()}=3 or {TEMPLATE_NAME:sensor.fan.status[{#SNMPINDEX}].last()}=4` |AVERAGE | |
|{#SNMPVALUE}: Fan is in warning state |<p>Please check the fan unit</p> |`{TEMPLATE_NAME:sensor.fan.status[{#SNMPINDEX}].last()}=2` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Fan is in critical state</p> |
|System name has changed (new name: {ITEM.VALUE}) |<p>System name has changed. Ack to close.</p> |`{TEMPLATE_NAME:system.name.diff()}=1 and {TEMPLATE_NAME:system.name.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`{TEMPLATE_NAME:system.hw.serialnumber.diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|Operating system description has changed |<p>Operating system description has changed. Possible reasons that system has been updated or replaced. Ack to close.</p> |`{TEMPLATE_NAME:system.sw.os.diff()}=1 and {TEMPLATE_NAME:system.sw.os.strlen()}>0` |INFO |<p>Manual close: YES</p><p>**Depends on**:</p><p>- System name has changed (new name: {ITEM.VALUE})</p> |
|{#ENT_NAME}: Device has been replaced (new serial number received) |<p>Device serial number has changed. Ack to close</p> |`{TEMPLATE_NAME:system.hw.serialnumber.diff()}=1 and {TEMPLATE_NAME:system.hw.serialnumber.strlen()}>0` |INFO |<p>Manual close: YES</p> |
|{#SNMPVALUE}: High memory utilization ( >{$MEMORY.UTIL.MAX}% for 5m) |<p>The system is running out of free memory.</p> |`{TEMPLATE_NAME:vm.memory.util[{#SNMPINDEX}].min(5m)}>{$MEMORY.UTIL.MAX}` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): High input error rate ( > {$IF.ERRORS.WARN:"{#IFNAME}"} for 5m) |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`{TEMPLATE_NAME:net.if.in.errors[{#SNMPINDEX}].min(5m)}>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.in.errors[{#SNMPINDEX}].max(5m)}<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High inbound bandwidth usage ( > {$IF.UTIL.MAX:"{#IFNAME}"}% ) |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`({TEMPLATE_NAME:net.if.in[{#SNMPINDEX}].avg(15m)}>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*{TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}) and {TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}>0 `<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.in[{#SNMPINDEX}].avg(15m)}<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*{TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}` |WARNING |<p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High output error rate ( > {$IF.ERRORS.WARN:"{#IFNAME}"} for 5m) |<p>Recovers when below 80% of {$IF.ERRORS.WARN:"{#IFNAME}"} threshold</p> |`{TEMPLATE_NAME:net.if.out.errors[{#SNMPINDEX}].min(5m)}>{$IF.ERRORS.WARN:"{#IFNAME}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.out.errors[{#SNMPINDEX}].max(5m)}<{$IF.ERRORS.WARN:"{#IFNAME}"}*0.8` |WARNING |<p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): High outbound bandwidth usage ( > {$IF.UTIL.MAX:"{#IFNAME}"}% ) |<p>The network interface utilization is close to its estimated maximum bandwidth.</p> |`({TEMPLATE_NAME:net.if.out[{#SNMPINDEX}].avg(15m)}>({$IF.UTIL.MAX:"{#IFNAME}"}/100)*{TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}) and {TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}>0 `<p>Recovery expression:</p>`{TEMPLATE_NAME:net.if.out[{#SNMPINDEX}].avg(15m)}<(({$IF.UTIL.MAX:"{#IFNAME}"}-3)/100)*{TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}` |WARNING |<p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Ethernet has changed to lower speed than it was before |<p>This Ethernet connection has transitioned down from its known maximum speed. This might be a sign of autonegotiation issues. Ack to close.</p> |`{TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].change()}<0 and {TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].last()}>0 and ( {TEMPLATE_NAME:net.if.type[{#SNMPINDEX}].last()}=6 or {TEMPLATE_NAME:net.if.type[{#SNMPINDEX}].last()}=7 or {TEMPLATE_NAME:net.if.type[{#SNMPINDEX}].last()}=11 or {TEMPLATE_NAME:net.if.type[{#SNMPINDEX}].last()}=62 or {TEMPLATE_NAME:net.if.type[{#SNMPINDEX}].last()}=69 or {TEMPLATE_NAME:net.if.type[{#SNMPINDEX}].last()}=117 ) and ({TEMPLATE_NAME:net.if.status[{#SNMPINDEX}].last()}<>2) `<p>Recovery expression:</p>`({TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].change()}>0 and {TEMPLATE_NAME:net.if.speed[{#SNMPINDEX}].prev()}>0) or ({TEMPLATE_NAME:net.if.status[{#SNMPINDEX}].last()}=2) ` |INFO |<p>**Depends on**:</p><p>- Interface {#IFNAME}({#IFALIAS}): Link down</p> |
|Interface {#IFNAME}({#IFALIAS}): Link down |<p>This trigger expression works as follows:</p><p>1. Can be triggered if operations status is down.</p><p>2. {$IFCONTROL:"{#IFNAME}"}=1 - user can redefine Context macro to value - 0. That marks this interface as not important. No new trigger will be fired if this interface is down.</p> |`{$IFCONTROL:"{#IFNAME}"}=1 and ({TEMPLATE_NAME:net.if.status[{#SNMPINDEX}].last()}=2)` |AVERAGE | |
|Interface {#IFNAME}({#IFALIAS}): In half-duplex mode |<p>Please check autonegotiation settings and cabling</p> |`{TEMPLATE_NAME:net.if.duplex[{#SNMPINDEX}].last()}=2` |WARNING | |
|{#SNMPVALUE}: Power supply is in critical state |<p>Please check the power supply unit for errors</p> |`{TEMPLATE_NAME:sensor.psu.status[{#SNMPINDEX}].last()}=3 or {TEMPLATE_NAME:sensor.psu.status[{#SNMPINDEX}].last()}=4` |AVERAGE | |
|{#SNMPVALUE}: Power supply is in warning state |<p>Please check the power supply unit for errors</p> |`{TEMPLATE_NAME:sensor.psu.status[{#SNMPINDEX}].last()}=2` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Power supply is in critical state</p> |
|Unavailable by ICMP ping |<p>Last three attempts returned timeout.  Please check device connectivity.</p> |`{TEMPLATE_NAME:icmpping.max(#3)}=0` |HIGH | |
|High ICMP ping loss |<p>-</p> |`{TEMPLATE_NAME:icmppingloss.min(5m)}>{$ICMP_LOSS_WARN} and {TEMPLATE_NAME:icmppingloss.min(5m)}<100` |WARNING |<p>**Depends on**:</p><p>- Unavailable by ICMP ping</p> |
|High ICMP ping response time |<p>-</p> |`{TEMPLATE_NAME:icmppingsec.avg(5m)}>{$ICMP_RESPONSE_TIME_WARN}` |WARNING |<p>**Depends on**:</p><p>- High ICMP ping loss</p><p>- Unavailable by ICMP ping</p> |
|{HOST.NAME} has been restarted (uptime < 10m) |<p>Uptime is less than 10 minutes</p> |`{TEMPLATE_NAME:system.uptime.last()}<10m` |WARNING |<p>Manual close: YES</p> |
|No SNMP data collection |<p>SNMP is not available for polling. Please check device connectivity and SNMP settings.</p> |`{TEMPLATE_NAME:zabbix[host,snmp,available].max({$SNMP.TIMEOUT})}=0` |WARNING | |
|{#SNMPVALUE}: Temperature is in critical state |<p>This trigger uses temperature sensor state</p> |`{TEMPLATE_NAME:sensor.temp.status[{#SNMPINDEX}].last()}=3 or {TEMPLATE_NAME:sensor.temp.status[{#SNMPINDEX}].last()}=4` |HIGH | |
|{#SNMPVALUE}: Temperature is in warning state |<p>This trigger uses temperature sensor state</p> |`{TEMPLATE_NAME:sensor.temp.status[{#SNMPINDEX}].last()}=2` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is in critical state</p> |
|{#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:"{#SNMPVALUE}"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`{TEMPLATE_NAME:sensor.temp.value[{#SNMPINDEX}].avg(5m)}>{$TEMP_CRIT:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[{#SNMPINDEX}].max(5m)}<{$TEMP_CRIT:"{#SNMPVALUE}"}-3` |HIGH | |
|{#SNMPVALUE}: Temperature is above warning threshold: >{$TEMP_WARN:"{#SNMPVALUE}"} |<p>This trigger uses temperature sensor values as well as temperature sensor status if available</p> |`{TEMPLATE_NAME:sensor.temp.value[{#SNMPINDEX}].avg(5m)}>{$TEMP_WARN:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[{#SNMPINDEX}].max(5m)}<{$TEMP_WARN:"{#SNMPVALUE}"}-3` |WARNING |<p>**Depends on**:</p><p>- {#SNMPVALUE}: Temperature is above critical threshold: >{$TEMP_CRIT:"{#SNMPVALUE}"}</p> |
|{#SNMPVALUE}: Temperature is too low: <{$TEMP_CRIT_LOW:"{#SNMPVALUE}"} |<p>-</p> |`{TEMPLATE_NAME:sensor.temp.value[{#SNMPINDEX}].avg(5m)}<{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}`<p>Recovery expression:</p>`{TEMPLATE_NAME:sensor.temp.value[{#SNMPINDEX}].min(5m)}>{$TEMP_CRIT_LOW:"{#SNMPVALUE}"}+3` |AVERAGE | |

## Feedback

Please report any issues with the template at https://support.zabbix.com

You can also provide a feedback, discuss the template or ask for help with it at [ZABBIX forums](https://www.zabbix.com/forum/zabbix-suggestions-and-feedback/418396-discussion-thread-for-official-zabbix-templates-for-cisco).

