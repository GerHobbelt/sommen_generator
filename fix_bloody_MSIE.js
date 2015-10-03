/*
     Thanks to
     
        http://www.quackit.com/css/properties/css_border-spacing.cfm
        
     for a quick place to try the various suggested solutions - none of which actually worked for me on my IE6, etc. setups.
*/     
      
function BrowserKludges() 
{
  var a;
  
  a = navigator.userAgent;
  
  this.Agent = a;
  this.isMSIE = (a.indexOf("MSIE") >= 0);
  
  return this;
}

function FixBrowserIssues()
{
    var tables;
    var i;
    var elem;
    var kludges; 
    
    kludges = new BrowserKludges();

    if (kludges.isMSIE)
    {
//alert("MSIE");
        tables = document.getElementsByTagName('table');
        for (i = 0; i < tables.length; i++)
        {
            try 
            { 
                // because the bloody IE6 bastard does not support CSS2 'border-spacing' :-(
                elem = tables[i].cellSpacing="0";
            } 
            catch (E) 
            {
            alert("Exception: " + E);
            }
        }
    }
	return;
}




