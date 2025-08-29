// Read a page's GET URL variables and return them as an associative array.
//http://jquery-howto.blogspot.com/2009/09/get-url-parameters-values-with-jquery.html

function getUrlVars()
{
    var vars = [], hash;
    var hashes = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
    for(var i = 0; i < hashes.length; i++)
    {
        hash = hashes[i].split('=');
        vars.push(hash[0]);
        vars[hash[0]] = hash[1];
    }
    return vars;
}


/*

http://www.example.com/?me=myValue&name2=SomeOtherValue


var first = getUrlVars()["me"];

// To get the second parameter
var second = getUrlVars()["name2"];



*/