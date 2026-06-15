from scapy.all import *
from datetime import datetime

INTERFACE = "sna"

PROTO_MAP = {
    6: "TCP",
    17: "UDP",
    1: "ICMP"
}


def process_packet(packet):
    if IP in packet:
        ts = datetime.now().strftime("%H:%M:%S")

        src = packet[IP].src
        dst = packet[IP].dst
        proto_num = packet[IP].proto
        proto = PROTO_MAP.get(proto_num, str(proto_num))

        length = len(packet)

        sport = ""
        dport = ""

        if TCP in packet:
            sport = packet[TCP].sport
            dport = packet[TCP].dport

        elif UDP in packet:
            sport = packet[UDP].sport
            dport = packet[UDP].dport

        print(
            f"[{ts}] "
            f"{proto:<4} "
            f"{src}:{sport} -> {dst}:{dport} "
            f"LEN={length}"
        )


sniff(
    iface=INTERFACE,
    prn=process_packet,
    store=False
)