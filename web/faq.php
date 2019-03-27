<html>
<head>
<title>ETF WebIDE</title>
		<style>
		h1, h2 { color: #cccccc; }
		p, ul, li {
			color: #cccccc; 
			font: 400 0.875rem/1.5 "Open Sans", sans-serif;
		}
		a { color: #dbb; }
		</style>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
</head>


<body bgcolor="#222222">
	<h1>C9 FAQ (Često postavljana pitanja)</h1>
	
	<h2>Index</h2>
	
	<p><b>Opšta pitanja</b><br>
	1. <a href="#bugreport">Kako prijaviti grešku</a><br><br>
	<b>Pristup c9 okruženju</b><br>
	2. <a href="#lozinka">Prilikom otvaranja stranice neprekidno iskače prozor za unos lozinke</a><br>
	3. <a href="#reinstall">Kada probam otvoriti C9 dobijem prozor za ponovnu instalaciju kao na slici ispod</a><br>
	4. <a href="#sporost">Čim otvorim C9 sve se stravično uspori i stoji beskonačno na reconnecting dok ostali normalno rade</a><br>
	5. <a href="#autosave">Izgleda da mi se kod ne snima automatski dok kucam, moram ručno ići na Save</a><br>
	6. <a href="#faledijelovi">Nešto sam kliknuo/la i sada ne mogu da dođem do nekih dijelova okruženja</a><br>
	7. <a href="#default">Izgubio/la sam neke panele i dijelove IDEa i generalno hoću da vratim na default izgled</a><br>
	8. <a href="#svnfifo">Kada uspijem ući u okruženje ono je neupotrebljivo, dobijam grešku &quot;Could not open file svn.fifo&quot;</a><br>
	9. <a href="#loginstranica">Login stranica c9.etf.unsa.ba se otvara beskonačno dugo, ili se dobije greška &quot;502 Gateway Error&quot;</a><br>
	10. <a href="#sporlogin">Kada probam da se logiram to traje beskonačno dugo, dobijem poruku &quot;Prijava traje duže nego uobičajeno...&quot; i ništa se ne dešava ni nakon više od minut</a><br><br>
	<b>Problemi sa kreiranjem zadataka (plugin Zadaci)</b><br>
	11. <a href="#nemazadace">Nije mi uopšte ponuđeno da kreiram aktuelnu zadaću</a><br>
	12. <a href="#folderpostoji">Ne mogu da kreiram zadatak kroz plugin Zadaci zbog poruke &quot;Folder postoji ali u njemu nema...&quot;</a><br>
	13. <a href="#zadacanista">Kada pokušam kreirati zadatak kroz plugin Zadaci ne desi se ništa, zadatak nije kreiran, ne postoji folder pod tim imenom</a><br>
	14. <a href="#nijezadaca">Ne mogu da pošaljem zadaću kroz C9, dobijam poruku da &quot;Trenutno izabrani projekat nije zadaća&quot;</a><br>
	15. <a href="#nematestova">Piše da za zadatak nisu definisani testovi iako kod drugih ima testova</a><br>
	16. <a href="#prosla_godina">Kako da pristupim zadacima od prošle godine?</a><br><br>
	<b>Čuvanje datoteka</b><br>
	17. <a href="#autosave2">Čini mi se da C9 ne zapisuje sve promjene koje radim</a><br>
	18. <a href="#reconnecting">Šta znači poruka &quot;Reconnecting&quot;?</a><br>
	19. <a href="#izgubljena">Sadržaj kompletne datoteke na kojoj sam radio/la satima je nestao! (ili je zamijenjen onim default programom). Undo opcija ne pomaže. Šta sada?</a><br>
	20. <a href="#undo">Može li se program vratiti na stariju verziju?</a><br>
	21. <a href="#brisanje">Da li će moji programi biti obrisani jednog dana? Moram li praviti backup svojih foldera?</a><br>
	22. <a href="#show_hidden">U stablu <i>workspace</i> pokazuju se neke datoteke sa čudnim imenima koja počinju tačkom</a><br><br>
	<b>Testiranje</b><br>
	23. <a href="#big_queue">Kada se pokuša testirati dobije se neki vrlo veliki broj zadataka koji čekaju na red za testiranje</a><br>
	24. <a href="#minimize_autotest">Ne mogu da testiram jer kada se klikne na karticu Autotest ne desi se ništa</a><br><br>
	<b>Korištenje debuggera</b><br>
	25. <a href="#could_not_open">Debugger se prekida sa porukom &quot;Could not open file...&quot;</a><br>
	26. <a href="#killing_all_inferiors">Debugger se stalno prekida sa porukom &quot;Killing All Inferiors&quot;</a><br>
	27. <a href="#shortcuts">Neki keyboard shortcuts (kratice na tastaturi) debuggera se poklapaju sa nečim što se već koristi</a><br>
	</p>
	
	<h2>Opšta pitanja</h2>
	
	<p><a name="bugreport"><i><big><b>Q:</b> Kako prijaviti grešku</big></i></a></p>
	<p><b>A:</b> Ako želite da prijavite grešku, molimo vas da koristite sljedeću proceduru da biste dobili <i>debug datoteku</i> koja je neophodna za rješavanje problema:<br>
	- Chrome: Pritisnite F12, zatim u prozoru koji se pojavi kliknite na karticu <b>Console</b>, te kliknite bilo gdje u prozor sa porukama desnim dugmetom miša i izaberite Save As.<br>
	- Firefox: Pritisnite F12, zatim u prozoru koji se pojavi kliknite na karticu <b>Console</b>, te kliknite bilo gdje u prozor sa porukama desnim dugmetom miša i izaberite prvo Select All, pa Copy, pa otvorite Notepad i izaberite Paste.</p>
	<p>Ovako kreiranu debug datoteku pošaljite na adresu vljubovic@etf.unsa.ba prilikom prijave problema.</p>
	<p>&nbsp;</p>
	
	<h2>Pristup c9 okruženju</h2>

	<p><a name="lozinka"><i><big><b>Q:</b> Prilikom otvaranja stranice neprekidno iskače prozor za unos lozinke</big></i></a></p>
	<p><b>A:</b> Preglednik bi trebalo da memoriše lozinku kada se prvi put unese. Ako se to nije desilo, mogući razlozi su:<br>
	- Ovaj problem se zna često dešavati kada više korisnika koristi isti računar. Koristite vlastiti računar. Ako ste nekome posudili računar radi konektovanja na c9, pobrišite historijske podatke (memorisane lozinke i sl.) i trebalo bi da radi poslije toga. U labu je dovoljno napraviti restart operativnog sistema da se lozinka pobriše. Ako više korisnika redovno dijeli računar, napravite odvojene korisničke račune ili barem koristite različite preglednike.<br>
	- Ako koristite "Privacy mode" odnosno "Incognito mode" lozinke se ne memorišu!<br>
	- Provjerite da u opcijama vašeg preglednika nije isključena opcija za memorisanje lozinki na stranicama.</p>
	<p>&nbsp;</p>

	<p><a name="reinstall"><i><big><b>Q:</b> Kada probam otvoriti C9 dobijem prozor za ponovnu instalaciju kao na slici ispod</big></i></a></p>
	<p><img src="static/images/content/install.png" width="680" height="412"></p>
	<p><b>A:</b> Zamolite tutora da uradi <b>reset konfiguracije</b>. To je jedna od opcija koje tutori imaju u admin panelu. Najprije uradite logout, pa neka tutor uradi reset konfiguracije, pa onda opet login. To ne mora biti vaš tutor, bilo ko od korisnika sa admin privilegijama može resetovati konfiguraciju nekog drugog korisnika jer je to generalno neškodljiva operacija (jedino što izgubite su neke prilagodbe koje ste eventualno radili u postavkama).</p>
	<p>&nbsp;</p>
	
	<p><a name="sporost"><i><big><b>Q:</b> Čim otvorim C9 sve se stravično uspori i stoji beskonačno na reconnecting dok ostali normalno rade</big></i></a></p>
	<p><a name="autosave"><i><big><b>Q:</b> Izgleda da mi se kod ne snima automatski dok kucam, moram ručno ići na Save</big></i></p>
	<p><a name="faledijelovi"><i><big><b>Q:</b> Nešto sam kliknuo/la i sada ne mogu da dođem do nekih dijelova okruženja</big></i></p>
	<p><b>A:</b> Na sva ova pitanja odgovor je isti kao za prethodno pitanje.</p>
	<p>&nbsp;</p>

	<p><a name="default"><i><big><b>Q:</b> Izgubio/la sam neke panele i dijelove IDEa i generalno hoću da vratim na default izgled</big></i></a></p>
	<p><b>A:</b> Ako vidite meni, izaberite Window &gt; Presets &gt; Full IDE. Moguće je i da je meni minimizovan u vidu jedne tanke sive trake između plave trake koja predstavlja C9 logotip i sadržaj prozora (na slici ispod). Dovoljno je da kliknete na tu traku da se meni ukaže.</p>
	<p><img src="static/images/content/meni.png" width="399" height="214"></p>
	<p>Ako ni to ne pomogne idite na reset konfiguracije (prethodno pitanje).</p>
	<p>&nbsp;</p>
	
	<p><a name="svnfifo"><i><big><b>Q:</b> Kada uspijem ući u okruženje ono je neupotrebljivo, a dobijam grešku kao na slici.</big></i></a></p>
	<p><img src="static/images/content/svn-fifo.png"><p>
	<p><b>A:</b> U vašem radnom prostoru nalazi se određeni broj skrivenih (Hidden) fajlova. Ovi fajlovi su skriveni s razlogom - ne biste ih trebali dirati ako ne razumijete o čemu je riječ! Jedan od tih fajlova zove se .svn.fifo i njegovim otvaranjem okruženje postaje privremeno neupotrebljivo jer je to <a href="http://man7.org/linux/man-pages/man7/fifo.7.html" target="_blank">poseban fajl tipa FIFO</a> (poznat još i kao <a href="https://en.wikipedia.org/wiki/Named_pipe" target="_blank">named pipe</a>) koji se ne može čitati na uobičajen način, a iz razloga prava pristupa mora se nalaziti u vašem folderu. Problem se rješava resetom konfiguracije.</p>
	<p>&nbsp;</p>
	
	<p><a name="loginstranica"><i><big><b>Q:</b> Login stranica c9.etf.unsa.ba se otvara beskonačno dugo, ili se dobije greška &quot;502 Gateway Error&quot;</big></i></a></p>
	<p><b>A:</b> Zbog ograničenja serverske arhitekture ETFa ovaj problem će se nažalost dešavati povremeno, ali ne bi trebao trajati duže od minut-dva. Ako potraje, pošaljite mail na adresu vljubovic@etf.unsa.ba ili se registrujte na stranici <a href="http://etf.ba">etf.ba</a> i potražite korisnika sa nickom <b>vedran</b>.</p>
	<p>&nbsp;</p>
	
	<p><a name="sporlogin"><i><big><b>Q:</b> Kada probam da se logiram to traje beskonačno dugo, dobijem poruku &quot;Prijava traje duže nego uobičajeno...&quot; i ništa se ne dešava ni nakon više od minut</big></i></a></p>
	<p><b>A:</b> Probajte se vratiti na login stranicu sa Back pa ponovo. Ako ni to ne pomogne, znači da postoje neke poteškoće u radu servera. Pošaljite mail na adresu vljubovic@etf.unsa.ba ili se registrujte na stranici <a href="http://etf.ba">etf.ba</a> i potražite korisnika sa nickom <b>vedran</b>.</p>
	<p>&nbsp;</p>

	
	<h2>Problemi sa kreiranjem zadataka (plugin Zadaci)</h2>
	
	<p><a name="nemazadace"><i><big><b>Q:</b> Nije mi uopšte ponuđeno da kreiram aktuelnu zadaću</big></i></a></p>
	<p><b>A:</b> Ako drugi studenti mogu kreirati zadaću, jedini razlog zašto se to može desiti je što vam je prikazana kartica Workspace s lijeve strane, pa trebate prebaciti na karticu Zadaci. Ako niko ne vidi zadaću na spisku u folderu Zadaci kontaktirajte tutora.</p>
	<p><img src="static/images/content/plugin_zadaci.png" width="252" height="264"></p>
	<p>&nbsp;</p>	
	
	<p><a name="folderpostoji"><i><big><b>Q:</b> Ne mogu da kreiram zadatak kroz plugin Zadaci zbog ove poruke</big></i></a></p>
	<p><img src="static/images/content/folder-exists.png" width="697" height="57"></p>
	<p><b>A:</b> Kliknite na plugin Workspace sa lijeve strane. Ako folder za zadatak postoji i prazan je (nema main.cpp u njemu) uradite desni klik na folder, izaberite Delete opciju. Sada bi trebalo da možete kreirati zadatak koristeći panel Zadaci s lijeve strane. Ako folder nije prazan, u njemu se vjerovatno nalazi fajl koji se zove različito od main.cpp (npr. main.CPP ili Main.cpp ili MAIN.CPP - razlika između velikih i malih slova je bitna). Preimenujte datoteku u main.cpp (sve malim slovima, bez razmaka) tako što ćete kliknuti desnim dugmetom miša na ime datoteke i izabrati opciju Rename. Ako folder ne postoji ili ako se datoteka zove baš main.cpp a ne nekako drugačije, kontaktirajte tutora.</p>
	<p>&nbsp;</p>	
	
	<p><a name="zadacanista"><i><big><b>Q:</b> Kada pokušam kreirati zadatak kroz plugin Zadaci ne desi se ništa, zadatak nije kreiran, ne postoji folder pod tim imenom</big></i></a></p>
	<p><b>A:</b> Pogledajte prvo pitanje <a href="#bugreport">Kako prijaviti grešku</a>.</p>
	<p>&nbsp;</p>	
	
	<p><a name="nijezadaca"><i><big><b>Q:</b> Ne mogu da pošaljem zadaću kroz C9, dobijam poruku da &quot;Trenutno izabrani projekat nije zadaća&quot;</big></i></a></p>
	<p><a name="nematestova"><i><big><b>Q:</b> Piše da za zadatak nisu definisani testovi iako kod drugih ima testova</big></i></a></p>
	<p><b>A:</b> Da bi se mogli testirati zadaci i slati zadaće na Zamger iz C9 potrebno je da sve zadatke kreirate kroz plugin Zadaci. Nije dovoljno da ručno kreirate folder sa odgovarajućim imenom. Sada ste već utrošili dosta vremena pišući kod pa postupite ovako: Sačuvajte sadržaj main fajla u npr. Notepadu, obrišite kompletan folder sa zadatkom (izaberite karticu Workspace, desni klik na ime foldera i Delete), kreirajte ponovo folder kroz plugin, a zatim vratite sadržaj iz Notepada. Ako je u pitanju zadaća ona neće biti prepisana jer se sve statistike rada vezuju za ime foldera, vaš folder je očito imao ispravno ime od početka tako da je sačuvano to da ste radili u njemu. Prethodna verzija fajla će biti ista kao nova, tako da neće biti razlike.</p>
	<p>&nbsp;</p>
	

	<p><a name="prosla_godina"><i><big><b>Q:</b> Kako da pristupim zadacima od prošle godine?</big></i></a></p>
	<p><b>A:</b> Kliknite na karticu Workspace.</p>
	<p>&nbsp;</p>

	
	<h2>Čuvanje datoteka</h2>
		
	<p><a name="autosave2"><i><big><b>Q:</b> Čini mi se da C9 ne zapisuje sve promjene koje radim</big></i></a></p>
	<p><a name="reconnecting"><i><big><b>Q:</b> Šta znači poruka &quot;Reconnecting&quot;?</big></i></a></p>
	<p><b>A:</b> Default postavka c9@etf servera je autosave - automatsko snimanje svih izmjena na serveru. Obratite pažnju na gornji dio ekrana u kojem piše ALL CHANGES SAVED.</p>
	<p><img src="static/images/content/saving.png" width="375" height="147"></p>
	<p>Nakon svakog otkucanog dijela teksta trebalo bi na ovom mjestu da kratko vidite SAVING a zatim ALL CHANGES SAVED. Ako se ovo ne dešava, znači da je autosave isključen za vaš profil, te kontaktirajte tutora kako bi izvršio <b>reset konfiguracije</b>.</p>
	<p>Ako u ovom dijelu stoji zelena oznaka i tekst ALL CHANGES SAVED, sve što ste otkucali je uredno zapisano na c9 serveru. Ako stoji žuta oznaka i tekst SAVING u toku je slanje na server. Ovo normalno traje djelić sekunde, ali ako je konekcija loša ili server preopterećen može trajati i duže. Savjetujemo da zaustavite kucanje ako uočite da status stoji na SAVING duže vremena. Ako u gornjem dijelu ekrana stoji crvena oznaka i tekst NOT SAVED, došlo je do greške na serveru prilikom snimanja i predlažemo da do sada otkucani tekst sačuvate u Notepadu a zatim da napravite reload c9 stranice.</p>
	<p><img src="static/images/content/refresh.png" width="420" height="259"></p>
	<p>U slučaju da problem potraje, kontaktirajte tutora ili potražite korisnika <b>vedran</b> na stranici etf.ba.</p>
	<p>Ako u gornjem dijelu ekrana vidite crveni prozor sa porukom <b>Reconnecting</b> to znači da je vaš web preglednik izgubio konekciju sa serverom. Sasvim je normalno da se ova poruka javi povremeno i odmah nestane (u prosjeku jednom na sat). Međutim, ako poruka traje duže to znači da je ili vaša Internet konekcija nestala ili je c9 server postao potpuno nedostupan. U svakom slučaju odmah prestanite sa kucanjem jer vaše izmjene neće biti zapamćene na serveru te pokušajte reload stranice.</p>
	<p>Ako se ništa od ovoga nije dešavalo odnosno dobijali ste poruke SAVED ali nekada u budućnosti ugledate staru verziju koda, pogledajte sljedeće pitanje.</p>	
	<p>&nbsp;</p>	
	
	<p><a name="izgubljena"><i><big><b>Q:</b> Sadržaj kompletne datoteke na kojoj sam radio/la satima je nestao! (ili je zamijenjen onim default programom). Undo opcija ne pomaže. Šta sada?</big></i></a></p>
	<p><a name="undo"><i><big><b>Q:</b> Može li se program vratiti na stariju verziju?</big></i></a></p>
	<p><b>A:</b> Ako ste probali koristiti <b>undo</b> opciju okruženja i nije pomoglo, zamolite tutora da vam <b>vrati prethodnu verziju</b> programa. Kroz admin panel tutor može doći do fajla main.cpp na kojem ste radili. Zatim u kartici SVN ima kompletan log svih izmjena koje ste radili. Odatle može vratiti prethodnu verziju. Obratite pažnju da se SVN dnevnik periodično prazni, tako da je bitno da što prije kontaktirate tutora, po mogućnosti odmah. Pored ove istorije dostupan je i vaš <b>git repozitorij</b> do kojeg možete doći kroz karticu <b>Changes</b> ili zamoliti tutora da vrati staru verziju datoteke sa Git-a. Na ovaj repozitorij možete ručno slati program, a inače se commit vrši automatski svako jutro oko 4:00.</p>
	<p>&nbsp;</p>
	
	<p><a name="brisanje"><i><big><b>Q:</b> Da li će moji programi biti obrisani jednog dana? Moram li praviti backup svojih foldera?</big></i></a></p>
	<p><b>A:</b> Vaši programi se redovno backupuju na drugom serveru. Ako korisnik jako dugo ne pristupa c9 (više od godinu), folder će biti obrisan sa servera ali se i dalje čuva backup. Dovoljno je da se javite administratoru da vam vrati folder iz backupa. Ako hoćete možete i sami napraviti backup koristeći opciju <b>File / Download Project</b> (prethodno se u kartici <b>Workspace</b> pozicionirajte na vaš polazni folder koji se također zove <b>workspace</b>).</p>
	<p>&nbsp;</p>

	<p><a name="show_hidden"><i><big><b>Q:</b> U stablu <i>workspace</i> pokazuju se neke suvišne datoteke i folderi čije ime počinje tačkom (kao na slici)</big></i></a></p>
	<p><img src="static/images/content/show_hidden.png" width="258" height="309"></p>
	<p><b>A:</b> Kliknite na ikonu sa zupčanikom, na slici označenu crvenom strelicom. Zatim pronađite u meniju opciju Show Hidden Files i isključite je.</p>
	<p>&nbsp;</p>

	
	<h2>Testiranje</h2>
		
	<p><a name="big_queue"><i><big><b>Q:</b> Kada se pokuša testirati dobije se neki vrlo veliki broj zadataka koji čekaju na red za testiranje</big></i></a></p>
	<p><b>A:</b> Ovo znači da je testni server nedostupan. Kako bi se rasteretio glavni c9 server testiranje programa se vrši na drugom serveru (testnom serveru). No ako je on isključen onda se programi ne mogu testirati. Pokušaćemo osposobiti testni server u najkraćem roku ali imajte na umu da testiranje u realnom vremenu nije zagarantovano.</p>
	<p>&nbsp;</p>
	
	<p><a name="minimize_autotest"><i><big><b>Q:</b> Ne mogu da testiram jer kada se klikne na karticu Autotest ne desi se ništa!</big></i></a></p>
	<p><b>A:</b> Moguće da je Autotest panel smanjen na veličinu 0 piksela. Samo postavite kursor miša na rub tako da se ukaže uobičajeni resize kursor i povucite :)</p>
	<p><img src="static/images/content/autotest-resize.png" width="295" height="300"></p>
	<p>&nbsp;</p>

	
	<h2>Upotreba debuggera</h2>
		
	<p><a name="could_not_open"><i><big><b>Q:</b> Debugger se stalno prekida sa porukom sličnom onoj ispod</big></i></a></p>
	<p><img src="static/images/content/cant_open_vector.png" width="514" height="158"></p>
	<p><b>A:</b> Obratite pažnju na razliku između opcija <b>Step Over (F10)</b> i <b>Step Into (F11)</b>. Ako debugger u svom koračanju kroz kod naiđe na poziv funkcije ili metode klase (što uključuje i konstruktor, pa tako npr. i deklaracija vektora je konstruktor), operacija Step Into će &quot;ući&quot; u kod te funkcije, a Step Over će je samo izvršiti i nastaviti dalje na sljedeću liniju ispod linije u kojoj je poziv funkcije. No ako je u pitanju bibliotečna funkcija ili bibliotečna klasa kao što je klasa <b>vector</b>, debugger ne može ući u njen kod. To je značenje greške iznad. Jednostavno u toj liniji nemojte kliknuti na Step Into niti pritisnuti tipku F11, nego kliknite na ikonicu lijevo od nje koja se zove Step Over kao što je ilustrovano na slici ispod, odnosno pritisnite tipku F10.</p>
	<p><img src="static/images/content/step_over.png" width="173" height="93"></p>
	<p>&nbsp;</p>
		
	<p><a name="killing_all_inferiors"><i><big><b>Q:</b> Debugger se stalno prekida sa porukom &quot;Killing All Inferiors&quot;</big></i></a></p>
	<p><b>A:</b> Kontaktirajte nas na adresu vljubovic@etf.unsa.ba sa sljedećim informacijama:
	<ul>
	<li>Izvorni kod datoteke na kojoj ste radili (ako je nećete mijenjati, dovoljno je da javite u kojem folderu se nalazi pa ćemo preuzeti direktno sa servera)</li>
	<li>U kojim sve linijama ste imali breakpoint (prekidnu tačku).</li>
	</ul>
	<p>Molimo vas da prije prijavljivanja greške povučete najnoviju verziju koda debuggera. Otvorite sljedeću stranicu:<br>
	<a href="https://c9.etf.unsa.ba/static/plugins/c9.ide.run.debug/debuggers/gdb/netproxy.js" target="_blank">https://c9.etf.unsa.ba/static/plugins/c9.ide.run.debug/debuggers/gdb/netproxy.js</a><br>
	Refreshujte sa F5 (ili Shift+F5 na Chromu) kako bi se povukla najnovija verzija sa servera umjesto cachirane. Datum koji se nalazi u 5-6 liniji treba biti što noviji. Nakon toga napravite refresh c9 okruženja (klikom na dugme &quot;Refresh&quot; web preglednika). Sada provjerite da li se greška i dalje dešava.</p>
	<p>Uočili smo da korištenje izuzetno velikog broja prekidnih tačaka može uzrokovati ovu grešku. Problem se sastoji u tome što Debugger čuva prekidne tačke u svim projektima koje ste ikada debugovali, iako ih ne vidite u kodu. Ove prekidne tačke možete vidjeti u donjem dijelu debugging prozora kao na slici:
	<p><img src="static/images/content/breakpoints.png" width="381" height="370"></p>
	<p>Potrebno je da sve suvišne prekidne tačke <i>pobrišete</i>, dakle nije dovoljno da kliknete na kvačicu lijevo od tačke, nego trebate kliknuti na crveni iksić koji se nalazi desno od mjesta na koje pokazuje strelica na slici.
	</p>
	<p>&nbsp;</p>
		
	<p><a name="shortcuts"><i><big><b>Q:</b> Neki keyboard shortcuts (kratice na tastaturi) debuggera se poklapaju sa nečim što se već koristi</big></i></a></p>
	<p><b>A:</b> Ovo svakako nije razlog da se ne koristi debugger. Možete uraditi jednu od sljedećih stvari:
	<ul>
	<li>Promijeniti shortcuts u toj drugoj aplikaciji koja je preuzela F5, F10, F11 itd. koji u svim integrisanim okruženjima predstavljaju standardne kratice za debugger.</li>
	<li>Promijeniti shortcuts u C9 okruženju. Otvorite Preferences (ikona sa točkom), u meniju s lijeve strane izaberite <b>Keybindings</b> a zatim u centralnom dijelu prozora skrolajte dok ne dođete do sekcije <b>Run &amp; Debug</b> (koja se nalazi pri kraju).</li>
	<li>Koristiti debugger klikanjem na ikonice umjesto tastaturom.</li>
	</ul>
	</p>
	<p>&nbsp;</p>
	
	<p><small><i>Last update: 17. 3. 2019. 14:50</i></small></p>
</body>
</html>
