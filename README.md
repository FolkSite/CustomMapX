# CustomMapX
Custom Google Map Style for MODX. Pulls Markers from Resource Template Variables.

**Simplified Version from https://github.com/craftsmancoding/gmarker/wiki/Gmarker-Snippet**

Customized with Map Colors and infoWindows

Set Google APIs for :
- Maps Embed
- Javascript API v3
- Static Maps

![map example](https://dl.dropboxusercontent.com/u/4277345/MODX/CustomMapX/map-example.png)

---

##Snippet

**snippet.Gmarker.php**

1. Around Line 285 are some variables for you to edit. 
2. Add your API Key to the `GmarkerUrl` variable
3. `lat_tv` & `lng_tv` should be what you set your TV name too
4. Same with `formatting_string`

##Chunks

**g_out**

Your Map wrapper HTML

**ginfo-all-custom**

Custom Info Box Bubble Template.

**gmarker-all-custom**

Marker Template. 

**gmarkershead-custom**

JS Map Setup. 

1. `color_map` can be used to set custom colors. If you take it out, also remove `styles: color_map,` on line 44. Also, `map type` must be ROADMAP for the colors to work

**infoBox-custom**

Custom Function for InfoBox Popup. These Images can be changed. 

1. line 81 - `div.style.background = "url('assets/app/img/map-bubble.png')";` 
2. line 98 - `closeImg.src = "assets/app/img/closebigger.gif";`

---

##Template Variables

![template variables](https://dl.dropboxusercontent.com/u/4277345/MODX/CustomMapX/custom-map-tv.png)

Set up these TVs for the resources your going to pull

---

##Call it

`&latlng` should be your center

```
    [[Gmarker? 
        &latlng=`LAT,LNG`
        &headTpl=`gmarkershead-custom` 
        &markerTpl=`gmarker-all-custom` 
        &infoTpl=`ginfo-all-custom`
        &zoom=`4` 
        &parents=`8`
        &limit=`0`
        &tvPrefix=`tv.`
        &height=`600`
        &width=`100`
        &apikey=`API_KEY`
    ]]
```

---

![blue marker](https://dl.dropboxusercontent.com/u/4277345/MODX/CustomMapX/map-marker-blue.png)

![red marker](https://dl.dropboxusercontent.com/u/4277345/MODX/CustomMapX/map-marker-red.png)

![info bubble](https://dl.dropboxusercontent.com/u/4277345/MODX/CustomMapX/map-bubble.png)

![X](https://dl.dropboxusercontent.com/u/4277345/MODX/CustomMapX/closebigger.gif)

