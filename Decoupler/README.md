# Entkoppler für IP-Symcon | Decoupler for IP-Symcon

Quellvariablen Filtern und auf eine neue Variable Mappen.
Wer kennt es nicht. Man hat ein Gerät und das Gerät hat Variablen. Man loggt diese Variable Tage, Monate oder Jahre. Dann geht das Gerät kaputt. Man tauscht es aus und ja man löscht eventuell das Gerät und das Archiv ist eventuell auch weg.
Oder man verwendet diese Variable in etlichen Scripten oder Automationen jeglicher Art.
Oder man bekommt ab und zu Werte von diesem Gerät die absolut nicht in dem Bereich sind wo sie sein sollten. Statt maximal 1000 Watt von diesem Gerät bekommt man einen Mond-Wert von 85965824584. Wenn das auffällt geht`s ans Archiv und man muss diesen Wert suchen und löschen.
Ich möchte in Zukunft einige Werte "Entkoppeln".
Das Modul ermöglicht:
    - Ausfiltern von Werten Unterhalb eines Wertes
    - Ausfiltern von Werten Oberhalb eines Wertes
    - Invertieren des Entkoppelten Wertes
    - Initial wird das Profil und Typ in die Entkoppelte Variable übernommen
    - Jegliche anschließende Veränderung durch Typ oder Profiländerung kann über eine Checkbox unterbunden werden

---

Map and Filter a Variable to a new Variable.
