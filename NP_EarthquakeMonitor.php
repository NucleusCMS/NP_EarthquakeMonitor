<?php
/*
    Plugin Name: Earthquakemonitor
    Description: Earthquake Monitor shows an overview of earthquakes around the world from the U.S. Geological Surveys data. 

    Author URI: http://www.yellownote.nl
    Copyright 2011  Cris van Geel  (email : cm.v.geel@gmail.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.
    
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

class NP_EarthquakeMonitor extends NucleusPlugin
{
    function getName()           { return 'Earthquake Monitor'; }
    function getAuthor()         { return 'Cris van Geel'; }
    function getURL()            { return 'http://www.yellownote.nl/'; }
    function getVersion()        { return '1.2'; }
    function getDescription()    { return 'Earthquake Monitor shows an overview of earthquakes around the world from the U.S. Geological Survey?s data. Just add <%EarthquakeMonitor%> in your skin and you are ready to go.';    }
    function getMinNucleusVersion()        { return '330';  }
    
    function install() 
    {
        $this->createOption('feed','Select the type of feed you want to show','select','eqs7day-M2.5' , 'Magnitude 0+ (Past hour)|eqs1hour-M0|Magnitude 1+ (Past hour)|eqs1hour-M1|Magnitude 0+ (Past day)|eqs1day-M0|Magnitude 1+ (Past day)|eqs1day-M1|Magnitude 2.5+ (Past day)|eqs1day-M2.5|Magnitude 2.5+ (Past 7 days)|eqs7day-M2.5|Magnitude 5+ (Past 7 days)|eqs7day-M5|Magnitude 7+ (Past 7 days)|eqs7day-M7');
        $this->createOption('maxitems', 'Max. earthquakes to show (0 = All)', 'text', '5', 'datatype=numerical');
        $this->createOption('linkable', 'Make earthquakes linkable to source site? (To show more details)', 'yesno', 'yes');
        $this->createOption('newwindow', 'Open links in new window?', 'yesno', 'yes');
        $this->createOption('noearthquakes', 'Text to show when there are no earthquakes to report.', 'text', 'No earthquakes.');
        $this->createOption('trim', 'Show only the first xx characters of text (end with ..) (0 = No trim)', 'text', '27','datatype=numerical');
        $this->createOption('cachetimer', 'Cachetimer in seconds for feed (Keep in mind when you change feed!)', 'text', '3600','datatype=numerical');
        $this->createOption('showtitle', 'Show title?', 'yesno', 'yes');
        $this->createOption('filter', 'Location filter (Case sensitive , 1 word only : i.e Japan . Leave empty for no filter)', 'text', '');
        $this->createOption('lastupdatetxt', 'Text "Last Update"', 'text', 'Last update :');
        $this->createOption('showupdate', 'Show last update text?', 'yesno', 'yes');
        $this->createOption('showupdateformat', 'Show last update format ( See http://php.net/manual/en/function.date.php )', 'text', 'D H:i:s (T)');
        $this->createOption('style', 'DL Class of the plugin (See Stylesheets)', 'text', 'sidebardl');
        
        $query = sprintf("CREATE TABLE IF NOT EXISTS %s (cachetime int(10) primary key default 0, data blob default '')", sql_table('plugin_earthquakemonitor'));
        sql_query($query);
        
        /* Fill initial dummy data */
        $query = sprintf("insert into %s (cachetime,data) values ('1','')", sql_table('plugin_earthquakemonitor'));
        sql_query($query);
    }
    
    function doSkinVar($skinType)
    {
        echo "<!-- Start EarthquakeMonitor plugin v".$this->getVersion()." -->\n";
        echo "<div class=\"sidebar\">\n";
        echo "<dl class=\"".$this->getOption('style')."\">\n";
        
        $arrayXML = $this->retrievexml();
        
        if ($this->getOption('showtitle') == "yes") 
        {
            echo "<dt>".$this->maintitle."</dt>\n";
        }
        
        $intCount = count($arrayXML);
        
        if ($intCount == 0)
            echo "<dd>".$this->GetOption('noearthquakes')."</dd>";
        
        if ($intCount > 0 and $intCount > $this->GetOption('maxitems') && $this->GetOption('maxitems') <> 0) 
            $max = $this->GetOption('maxitems');
        else
            $max = $intCount;
        
        foreach($arrayXML as $XML) {
            
            $title = $XML->title;
            
            if ($this->GetOption('trim') > 0 and strlen($title) > $this->GetOption('trim') ) 
                $title = substr($title,0,$this->getOption('trim')) . '..';
            
            if ($this->GetOption('linkable') == "no")
                echo "<dd>{$title}</dd>\n";
            
            else {
                if($this->GetOption('newwindow') == "yes")
                    $target = "_blank";
                else 
                    $target = "_top";
                $vs = array($target, $XML->description, $XML->title, $XML->link, $title);
                echo vsprintf('<dd><a target="%s" title="%s %s" href="%s">%s</a></dd>', $vs) . "\n";
            }
        }
        
        echo "</dl>\n";
        
        if ($this->GetOption('showupdate') == "yes") {
        
            $tmp_date = strtotime($this->lastupdate);
            $date = date($this->GetOption('showupdateformat'),$tmp_date);
            echo $this->GetOption('lastupdatetxt')." {$date}\n";
        }
        echo "</div>\n";
    }

    function supportsFeature ($what)
    {
        return in_array($what,array('SqlTablePrefix','SqlApi'));
    }

    
    function retrievexml() {
    
        /* First check if cached version is still valid */
        $query="select cachetime from ".sql_table('plugin_earthquakemonitor');
        $result= sql_query($query);
        $row=mysql_fetch_object($result);
        $cachedtime = $row->cachetime;
        if (time() - $this->GetOption('cachetimer') > $cachedtime) { 
            /* Refresh the cache */
            $tmpXML = file_get_contents('http://earthquake.usgs.gov/earthquakes/catalogs/'.$this->GetOption('feed').'.xml');
            $compressedXML = gzcompress($tmpXML);
            $query = "UPDATE ".sql_table('plugin_earthquakemonitor')." SET cachetime='".time()."',data='".addslashes($compressedXML)."';";
            sql_query($query);
        
        }
        else {
            /* Load from cache */
            $query="select data from ".sql_table('plugin_earthquakemonitor');
            $result= sql_query($query);
            $row=mysql_fetch_object($result);
            $tmpXML=stripslashes(gzuncompress($row->data));
        }

        $objXML = simplexml_load_string($tmpXML);
        
        /* Additional Filter */
        $result = $objXML->xpath("(//channel/item)[contains(., '".$this->GetOption('filter')."')]");

        $this->lastupdate = $objXML->channel->pubDate;
        $this->maintitle = $objXML->channel->title;
        return $result;
    }
    
    function array_to_object($array = array()) {
        return (object) $array;
    }
    
    function unInstall() { 
        //Drop the created table
        $query="DROP TABLE IF EXISTS ".sql_table('plugin_earthquakemonitor');
        sql_query($query);
    }
}
