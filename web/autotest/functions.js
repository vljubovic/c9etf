var utoku=0;
RegExp.quote = function(str) {
	return str.replace(/([.?*+^$[\]\\(){}|-])/g, "\\$1");
};
function daj_i_j(fullid) {
	// Saznaje redni broj AT-a, i redni broj expected izlaza (ako je postavljen)
	var niz=fullid.split("_");
	var povr=[];
	povr[0]=parseInt(niz[1], 10); // i
	if (niz.length>2)
		povr[1]=parseInt(niz[2], 10); // j
	return povr;
}

function brisivar(i, j) {
	$("#variant_"+i+"_"+j).remove();
	// Za sve one ciji je j veci od ovog, umanjiti j i smanjiti broj uz tekst
	j++;
	var brvar=parseInt($('[name="brvar_'+i+'"]').val(), 10); // broj varijanti ovog at-a
	$('[name="brvar_'+i+'"]').val(brvar-1);	
	while (j<=brvar) {
		$("#br_"+i+"_"+j).html(j-1);
		$("#br_"+i+"_"+j).attr("id","br_"+i+"_"+(j-1));
		$("#variant_"+i+"_"+j).attr("id","variant_"+i+"_"+(j-1));
		$("#bris_"+i+"_"+j).attr("id","bris_"+i+"_"+(j-1));
		$('[name="expected_'+i+'_'+j+'"]').attr("name","expected_"+i+"_"+(j-1));
		j++;
	}
}
function animate_brisivar(i, j) {
	$("#variant_"+i+"_"+j).hide('slow', function() {brisivar(i, j); utoku=0;});
}
function brisanjeVarijante(id_bris) {
	if (utoku) {
			alert("Pri�ekajte malo!");
			return 0;
		}
	utoku=1;
	var niz=daj_i_j(id_bris);
	var i=niz[0]; // Starta od 1
	var j=niz[1]; // Starta od 1
	animate_brisivar(i, j);	    			   	
}

function prepareVariant(template, i, j) {
	var re = new RegExp(RegExp.quote("NUMI"), "g");
	template=template.replace(re, i);
	re = new RegExp(RegExp.quote("NUMJ"), "g");
	template=template.replace(re, j);			
	return template;
}
function dodavanjeVarijante(id_dod) {
	if (utoku) {
		alert("Pricekajte malo!");
		return 0;
	}
	utoku=1;
	var niz=daj_i_j(id_dod);
	var i=niz[0]; // Starta od 1
	// Ubaciti varijantu sa i=i, j=brvar, ali brvar povecano za 1
	var brvar=parseInt($('[name="brvar_'+i+'"]').val(), 10);
	brvar++; // Uveca broj varijanti za ovaj AT
	$('[name="brvar_'+i+'"]').val(brvar);
	var j=brvar; // j je redni broj zadnje varijante, odgovara ukupnom broju varijanti
	$("#cell_"+i).append(prepareVariant($("#variant_template").html(), i, j));
	utoku=0;
}

function ukloniat(i) {
	$("#attabela_"+i).remove();
	var ukupnoatova=parseInt($("[name='numateova']").val(), 10);	    	
	$("[name='numateova']").val(ukupnoatova-1);
	if (ukupnoatova-1==0) {
		$("#sviATovi").html("<font class='tekst info'>Ako potvrdite izmjene nece biti definiranih AT-ova za ovaj zadatak. Obrisat ce se i postavke definirane iznad...</font><br><br>");
	}			
	prilagodi(i+1, ukupnoatova);	
}
function animate_ukloniat(i) {			
	$("#attabela_"+i).hide('slow', function() {ukloniat(i); utoku=0;});
}
function prilagodi(from, to) {
	for (k=from; k<=to; k++) {
		$("#attabela_"+k).attr("id", "attabela_"+(k-1));
		$("#atbr_"+k).html(k-1);
		$("#atbr_"+k).attr("id", "atbr_"+(k-1));
		$("#atbris_"+k).attr("id", "atbris_"+(k-1));
		$("[name='require_symbols_"+k+"']").attr("name", "require_symbols_"+(k-1));
		$("[name='replace_symbols_"+k+"']").attr("name", "replace_symbols_"+(k-1));
		$("[name='code_"+k+"']").attr("name", "code_"+(k-1));
		$("[name='global_above_main_"+k+"']").attr("name", "global_above_main_"+(k-1));
		$("[name='global_top_"+k+"']").attr("name", "global_top_"+(k-1));
		$("[name='timeout_"+k+"']").attr("name", "timeout_"+(k-1));
		$("[name='vmem_"+k+"']").attr("name", "vmem_"+(k-1));
		$("[name='stdin_"+k+"']").attr("name", "stdin_"+(k-1));
		$("#cell_"+k).attr("id", "cell_"+(k-1));
		$("[name='brvar_"+k+"']").attr("name", "brvar_"+(k-1));
		for (t=1; t<=parseInt($("[name='brvar_"+(k-1)+"']").val(), 10); t++) {
			$("#variant_"+k+"_"+t).attr("id", "variant_"+(k-1)+"_"+t);
			$("#br_"+k+"_"+t).attr("id", "br_"+(k-1)+"_"+t);
			$("#bris_"+k+"_"+t).attr("id", "bris_"+(k-1)+"_"+t);
			$("[name='expected_"+k+"_"+t+"']").attr("name", "expected_"+(k-1)+"_"+t);
		}
		$("#dodvar_"+k).attr("id", "dodvar_"+(k-1));
		$("[name='expected_exception_"+k+"']").attr("name", "expected_exception_"+(k-1));
		$("[name='expected_crash_"+k+"']").attr("name", "expected_crash_"+(k-1));	
		$("[name='ignore_whitespace_"+k+"']").attr("name", "ignore_whitespace_"+(k-1));				
		$("[name='regex_"+k+"']").attr("name", "regex_"+(k-1));	
		$("[name='substring_"+k+"']").attr("name", "substring_"+(k-1));
	}
}
function atbrisi(at_id) {
	// Obriše se kompletna tabela
	// Promijeni redni broj AT-a, i sve gdje se i spominje za svaki od AT-ova ispod
	// Umanji numateova za 1
	if (utoku) {
		alert("Pricekajte malo!");
		return 0;
	}
	utoku=1;
	var niz=daj_i_j(at_id);
	var i=niz[0]; // Starta od 1	    	
	animate_ukloniat(i); // Uklanjanje AT-a broj i
}

function prepareAt(template, i) {
	var re = new RegExp(RegExp.quote("NUMI"), "g");
	template=template.replace(re, i);						
	return template;
}
function dodavanjeata() {
	if (utoku) {
		alert("Pricekajte malo!");
		return 0;
	}
	utoku=1;
	var ukupnoatova=parseInt($("[name='numateova']").val(), 10);
	if (ukupnoatova==0) {
		$("#sviATovi").html(""); // Obriše onaj info tekst koji se pojavi kad nema AT-ova
	}	
	ukupnoatova++;    	
	$("[name='numateova']").val(ukupnoatova);
	// template treba uzeti, popuniti vrijednostima i pa ga prosto ubaciti
	$("#sviATovi").append(prepareAt($("#at_template").html(), ukupnoatova));
	$('html, body').animate({
        scrollTop: $("[id^=attabela_]").last().offset().top
    }, 'slow');
	utoku=0;
}
function showAdvanced() {
	if (advanced==0) {
		// Treba prikazati dodatne opcije
		advanced=1;
		$("[name='adv_button']").val("Sakrij dodatne opcije");
		$("[name='adv_display']").css("display", "table-row");
		$("[name='adv']").val(advanced);
	} else {
		// Treba sakriti dodatne opcije 
		advanced=0;
		$("[name='adv_button']").val("Prikaži dodatne opcije");
		$("[name='adv_display']").css("display", "none");
		$("[name='adv']").val(advanced);
	}
}
function safeLinkBackForw(gdje) {
    var currentUrl = window.location.href;
    history.go(gdje);
    setTimeout(function(){
        // if location was not changed in 100 ms, then there is no history back
        if(currentUrl === window.location.href){
            // redirect to site root
            window.open("index.php","_top");
        }
    }, 2500);
}
function get_browser_info() {
    var ua = navigator.userAgent, tem, M = ua.match(/(opera|chrome|safari|firefox|msie|trident(?=\/))\/?\s*(\d+)/i) || [];
    if (/trident/i.test(M[1])) {
        tem = /\brv[ :]+(\d+)/g.exec(ua) || [];
        return {name: 'IE ', version: (tem[1] || '')};
    }
    if (M[1] === 'Chrome') {
        tem = ua.match(/\bOPR\/(\d+)/)
        if (tem != null) {
            return {name: 'Opera', version: tem[1]};
        }
    }
    M = M[2] ? [M[1], M[2]] : [navigator.appName, navigator.appVersion, '-?'];
    if ((tem = ua.match(/version\/(\d+)/i)) != null) {
        M.splice(1, 1, tem[1]);
    }
    return {
        name: M[0],
        version: M[1]
    };
}
browser = get_browser_info();
function histReload() {
	vrj=parseInt(document.getElementById("historija").value,10);
	if (vrj == 0) {
		// Nije historija
    	document.getElementById('historija').value = "1";    	
    	// Zbog univerzalnosti koda, jer FF pamti vrijednosti polja i nakon reloada
    	// Za druge browsere su komande ispod visak    		 
    	if (advanced==1) {
			// Treba prikazati dodatne opcije
			$("[name='adv_button']").val("Sakrij dodatne opcije");
			$("[name='adv_display']").css("display", "table-row");
			$("[name='adv']").val(advanced);
		} else {
			// Treba sakriti dodatne opcije 
			$("[name='adv_button']").val("Prikaži dodatne opcije");
			$("[name='adv_display']").css("display", "none");
			$("[name='adv']").val(advanced);
		}
    } else {
    	// Jeste historija, treba refresh
    	document.getElementById('historija').value = "0";
    	setTimeout(function () { // Zbog univerzalnosti koda reload se nalazi u setTimeoutu, jer samo tako ce funkcionisati na FF
    		window.location.reload();
    	}, 0);
    }
}
function historija() { // Za Firefox ima skroz odvojen pageshow event, pa ova funkcija za Firefox ne treba nista da radi
	if (browser.name.indexOf("Firefox") == -1) {
		histReload();
	}
}
function FFhistorija() {
	histReload();
}
function naVrh() {
	$("html, body").animate({ scrollTop: 0 }, "slow");
}
function naDno() {
	$("html, body").animate({ scrollTop: $(document).height() }, "slow");
}