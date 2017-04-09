<html>
<head>
<title>ETF WebIDE</title>
		<style>
		h1 { color: #cccccc; }
		p {
			color: #cccccc; 
			font: 400 0.875rem/1.5 "Open Sans", sans-serif;
		}
		</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>


<body bgcolor="#222222">
	<h1>C9 FAQ (Često postavljana pitanja)</h1>
	<p><i><big><b>Q:</b> Kako prijaviti grešku?</big></i></p>
	<p><b>A:</b> Ako želite da prijavite grešku, molimo vas da koristite sljedeću proceduru da biste dobili <i>debug datoteku</i> koja je neophodna za rješavanje problema:<br>
	- Chrome: Pritisnite F12, zatim u prozoru koji se pojavi kliknite na karticu <b>Console</b>, te kliknite bilo gdje u prozor sa porukama desnim dugmetom miša i izaberite Save As.<br>
	- Firefox: Pritisnite F12, zatim u prozoru koji se pojavi kliknite na karticu <b>Console</b>, te kliknite bilo gdje u prozor sa porukama desnim dugmetom miša i izaberite prvo Select All, pa Copy, pa otvorite Notepad i izaberite Paste.</p>
	<p>Ovako kreiranu debug datoteku pošaljite prilikom prijave problema.</p>
	<p>&nbsp;</p>

	<p><i><big><b>Q:</b> Prilikom otvaranja stranice neprekidno iskače prozor za unos lozinke</big></i></p>
	<p><b>A:</b> Preglednik bi trebalo da memoriše lozinku kada se prvi put unese. Ako se to nije desilo, mogući razlozi su:<br>
	- Ovaj problem se zna često dešavati kada više korisnika koristi isti računar. Koristite vlastiti računar. Ako ste nekome posudili računar radi konektovanja na c9, pobrišite historijske podatke (memorisane lozinke i sl.) i trebalo bi da radi poslije toga. U labu je dovoljno napraviti restart operativnog sistema da se lozinka pobriše. Ako više korisnika redovno dijeli računar, napravite odvojene korisničke račune ili barem koristite različite preglednike.<br>
	- Pobrinite se da ne koristite "Privacy mode" odnosno "Incognito mode".<br>
	- Provjerite da u opcijama vašeg preglednika nije isključena opcija za memorisanje lozinki na stranicama.</p>
	<p>&nbsp;</p>

	<p><i><big><b>Q:</b> Ne mogu da testiram jer kada se klikne na dugme Autotest ne desi se ništa!</big></i></p>
	<p><b>A:</b> Moguće da je Autotest panel smanjen na veličinu 0 piksela. Samo postavite kursor miša na rub tako da se ukaže uobičajeni resize kursor i povucite :)</p>
	<p><img src="static/images/content/autotest-resize.png" width="295" height="300"></p>
	<p>&nbsp;</p>

	<p><i><big><b>Q:</b> Izgubio/la sam neke panele i dijelove IDEa i generalno hoću da vratim na default izgled.</big></i></p>
	<p><b>A:</b> Ako vidite meni, izaberite Window &gt; Presets &gt; Full IDE. Moguće je i da je meni minimizovan u vidu jedne tanke sive trake između plave trake koja predstavlja C9 logotip i sadržaj prozora (na slici ispod). Dovoljno je da kliknete na tu traku da se meni ukaže.</p>
	<p><img src="static/images/content/meni.png" width="399" height="214"></p>
	<p>Ako ni to ne pomogne idite na reset konfiguracije (sljedeće pitanje).</p>
	<p>&nbsp;</p>

	<p><i><big><b>Q:</b> U stablu <i>workspace</i> pokazuju se neke suvišne datoteke i folderi čije ime počinje tačkom (kao na slici).</big></i></p>
	<p><img src="static/images/content/show_hidden.png" width="258" height="309"></p>
	<p><b>A:</b> Kliknite na ikonu sa zupčanikom, na slici označenu crvenom strelicom. Zatim pronađite u meniju opciju Show Hidden Files i isključite je.</p>
	<p>&nbsp;</p>

	<p><i><big><b>Q:</b> Kada probam otvoriti C9 dobijem prozor za ponovnu instalaciju kao na slici ispod.</big></i></p>
	<p><img src="static/images/content/install.png" width="680" height="412"></p>
	<p><b>A:</b> Zamolite tutora da uradi <b>reset konfiguracije</b>. To je jedna od opcija koje tutori imaju u admin panelu. Najprije uradite logout, pa neka tutor uradi reset konfiguracije, pa onda opet login. To ne mora biti vaš tutor, bilo ko od korisnika sa admin privilegijama može resetovati konfiguraciju nekog drugog korisnika jer je to generalno neškodljiva operacija (jedino što izgubite su neke prilagodbe koje ste eventualno radili u postavkama).</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Čim otvorim C9 sve se stravično uspori i stoji beskonačno na reconnecting dok ostali normalno rade.</big></i></p>
	<p><i><big><b>Q:</b> Izgleda da mi se kod ne snima automatski dok kucam, moram ručno ići na Save.</big></i></p>
	<p><i><big><b>Q:</b> Nešto sam kliknuo/la i sada ne mogu da dođem do nekih dijelova okruženja.</big></i></p>
	<p><b>A:</b> Na sva ova pitanja odgovor je isti kao za prethodno pitanje.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Kada uspijem ući u okruženje ono je neupotrebljivo, a dobijam grešku kao na slici.</big></i></p>
	<p><img src="static/images/content/svn-fifo.png"><p>
	<p><b>A:</b> U vašem radnom prostoru nalazi se određeni broj skrivenih (Hidden) fajlova. Ovi fajlovi su skriveni s razlogom - ne biste ih trebali dirati ako ne razumijete o čemu je riječ! Jedan od tih fajlova zove se .svn.fifo i njegovim otvaranjem okruženje postaje privremeno neupotrebljivo jer je to <a href="http://man7.org/linux/man-pages/man7/fifo.7.html" target="_blank">poseban fajl tipa FIFO</a> (poznat još i kao <a href="https://en.wikipedia.org/wiki/Named_pipe" target="_blank">named pipe</a>) koji se ne može čitati na uobičajen način, a iz razloga prava pristupa mora nalaziti u vašem folderu. Problem se rješava resetom konfiguracije.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Ne mogu da pošaljem zadaću kroz C9, dobijam poruku da &quot;Trenutno izabrani projekat nije zadaća&quot;.</big></i></p>
	<p><i><big><b>Q:</b> Piše da za zadatak nisu definisani testovi iako kod drugih ima testova.</big></i></p>
	<p><b>A:</b> Da bi se mogli testirati zadaci i slati zadaće na Zamger iz C9 potrebno je da sve zadatke kreirate kroz plugin Zadaci. Nije dovoljno da ručno kreirate folder sa odgovarajućim imenom. Sada ste već utrošili dosta vremena pišući kod pa postupite ovako: Sačuvajte sadržaj main fajla u npr. Notepadu, obrišite kompletan folder sa zadatkom (izaberite karticu Workspace, desni klik na ime foldera i Delete), kreirajte ponovo folder kroz plugin, a zatim vratite sadržaj iz Notepada. Ako je u pitanju zadaća ona neće biti prepisana jer se sve statistike rada vezuju za ime foldera, vaš folder je očito imao ispravno ime od početka tako da je sačuvano to da ste radili u njemu. Prethodna verzija fajla će biti ista kao nova, tako da neće biti razlike.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Ne mogu da kreiram zadatak kroz plugin Zadaci zbog ove poruke.</big></i></p>
	<p><img src="static/images/content/folder-exists.png" width="697" height="57"></p>
	<p><b>A:</b> Kliknite na plugin Workspace sa lijeve strane. Ako folder za zadatak postoji i prazan je (nema main.cpp u njemu) uradite desni klik na folder, izaberite Delete opciju. Sada bi trebalo da možete kreirati zadatak koristeći panel Zadaci s lijeve strane. Ako folder nije prazan, u njemu se vjerovatno nalazi fajl koji se zove različito od main.cpp (npr. main.CPP ili Main.cpp ili MAIN.CPP - razlika između velikih i malih slova je bitna). Preimenujte datoteku u main.cpp (sve malim slovima, bez razmaka) tako što ćete kliknuti desnim dugmetom miša na ime datoteke i izabrati opciju Rename. Ako folder ne postoji ili ako se datoteka zove baš main.cpp a ne nekako drugačije, kontaktirajte tutora.</p>
	<p>&nbsp;</p>	
	
	<p><i><big><b>Q:</b> Kada pokušam kreirati zadatak kroz plugin Zadaci ne desi se ništa, zadatak nije kreiran, ne postoji folder pod tim imenom</big></i></p>
	<p><b>A:</b> Na stranici etf.ba kontaktirajte korisnika sa nickom <b>vedran</b>.</p>
	<p>&nbsp;</p>	
	
	<p><i><big><b>Q:</b> Sve što sam radio/la nije se zapamtilo. Da li C9 zapisuje ono što radim?</big></i></p>
	<p><b>A:</b> Default postavka c9@etf servera je autosave - automatsko snimanje svih izmjena na serveru. Obratite pažnju na gornji dio ekrana u kojem piše ALL CHANGES SAVED.</p>
	<p><img src="static/images/content/saving.png" width="375" height="147"></p>
	<p>Nakon svakog otkucanog dijela teksta trebalo bi na ovom mjestu da kratko vidite SAVING a zatim ALL CHANGES SAVED. Ako se ovo ne dešava, znači da je autosave isključen za vaš profil, te kontaktirajte tutora kako bi izvršio <b>reset konfiguracije</b>.</p>
	<p>Ako u ovom dijelu stoji zelena oznaka i tekst ALL CHANGES SAVED, sve što ste otkucali je uredno zapisano na c9 serveru. Ako stoji žuta oznaka i tekst SAVING u toku je slanje na server. Ovo normalno traje djelić sekunde, ali ako je konekcija loša ili server preopterećen može trajati i duže. Savjetujemo da zaustavite kucanje ako uočite da status stoji na SAVING duže vremena. Ako u gornjem dijelu ekrana stoji crvena oznaka i tekst NOT SAVED, došlo je do greške na serveru prilikom snimanja i predlažemo da do sada otkucani tekst sačuvate u Notepadu a zatim da napravite reload c9 stranice.</p>
	<p><img src="static/images/content/refresh.png" width="420" height="259"></p>
	<p>U slučaju da problem potraje, kontaktirajte tutora ili potražite korisnika <b>vedran</b> na stranici etf.ba.</p>
	<p>Ako u gornjem dijelu ekrana vidite crveni prozor sa porukom <b>Reconnecting</b> to znači da je vaš web preglednik izgubio konekciju sa serverom. Sasvim je normalno da se ova poruka javi povremeno i odmah nestane (u prosjeku jednom na sat). Međutim, ako poruka traje duže to znači da je ili vaša Internet konekcija nestala ili je c9 server postao potpuno nedostupan. U svakom slučaju odmah prestanite sa kucanjem jer vaše izmjene neće biti zapamćene na serveru te pokušajte reload stranice.</p>
	<p>Ako se ništa od ovoga nije dešavalo odnosno dobijali ste poruke SAVED ali nekada u budućnosti ugledate staru verziju koda, pogledajte sljedeće pitanje.</p>	
	<p>&nbsp;</p>	
	
	<p><i><big><b>Q:</b> Sadržaj kompletne datoteke na kojoj sam radio/la satima je nestao! (ili je zamijenjen onim default programom). Undo opcija ne pomaže. Šta sada?</big></i></p>
	<p><b>A:</b> Zamolite tutora da vam <b>vrati prethodnu verziju</b> programa. Kroz admin panel tutor može doći do fajla main.cpp na kojem ste radili. Zatim u kartici SVN ima kompletan log svih izmjena koje ste radili. Odatle može vratiti prethodnu verziju. Obratite pažnju da se SVN dnevnik periodično prazni, tako da je bitno da što prije kontaktirate tutora, po mogućnosti odmah.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Login stranica c9.etf.unsa.ba se otvara beskonačno dugo, ili se dobije greška &quot;502 Gateway Error&quot;</big></i></p>
	<p><b>A:</b> Zbog ograničenja serverske arhitekture ETFa ovaj problem će se nažalost dešavati povremeno, ali ne bi trebao trajati duže od minut-dva. Ako potraje, pošaljite mail na adresu vljubovic@etf.unsa.ba ili se registrujte na stranici <a href="http://etf.ba">etf.ba</a> i potražite korisnika sa nickom <b>vedran</b>.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Kada probam da se logiram to traje beskonačno dugo, dobijem poruku &quot;Prijava traje duže nego uobičajeno...&quot; i ništa se ne dešava ni nakon više od minut</big></i></p>
	<p><b>A:</b> Probajte se vratiti na login stranicu sa Back pa ponovo. Ako ni to ne pomogne, znači da postoje neke poteškoće u radu servera. Pošaljite mail na adresu vljubovic@etf.unsa.ba ili se registrujte na stranici <a href="http://etf.ba">etf.ba</a> i potražite korisnika sa nickom <b>vedran</b>.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Kada se pokuša testirati dobije se neki vrlo veliki broj zadataka koji čekaju na red za testiranje</big></i></p>
	<p><b>A:</b> Ovo znači da je testni server nedostupan. Kako bi se rasteretio glavni c9 server testiranje programa se vrši na drugom serveru (testnom serveru). No ako je on isključen onda se programi ne mogu testirati. Pokušaćemo osposobiti testni server u najkraćem roku ali imajte na umu da testiranje u realnom vremenu nije zagarantovano.</p>
	<p>&nbsp;</p>
	
	<p><i><big><b>Q:</b> Debugger se stalno prekida sa porukom sličnom onoj ispod</big></i></p>
	<p><img src="static/images/content/cant_open_vector.png" width="514" height="158"></p>
	<p><b>A:</b> Obratite pažnju na razliku između opcija <b>Step Over (F10)</b> i <b>Step Into (F11)</b>. Ako debugger u svom koračanju kroz kod naiđe na poziv funkcije ili metode klase (što uključuje i konstruktor, pa tako npr. i deklaracija vektora je konstruktor), operacija Step Into će &quot;ući&quot; u kod te funkcije, a Step Over će je samo izvršiti i nastaviti dalje na sljedeću liniju ispod linije u kojoj je poziv funkcije. No ako je u pitanju bibliotečna funkcija ili bibliotečna klasa kao što je klasa <b>vector</b>, debugger ne može ući u njen kod. To je značenje greške iznad. Jednostavno u toj liniji nemojte kliknuti na Step Into niti pritisnuti tipku F11, nego kliknite na ikonicu lijevo od nje koja se zove Step Over kao što je ilustrovano na slici ispod, odnosno pritisnite tipku F10.</p>
	<p><img src="static/images/content/step_over.png" width="173" height="93"></p>
	<p>&nbsp;</p>
	
	<p><small><i>Last update: 23. 3. 2017. 19:30</i></small></p>
</body>
</html>
