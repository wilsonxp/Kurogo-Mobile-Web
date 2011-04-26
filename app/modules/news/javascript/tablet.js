function moduleInit() {
    newsListScroller = new iScroll('stories');
    newsDetailScroller = new iScroll('storyDetailWrapper', {checkDOMChange: true});
    
    var links = document.querySelectorAll("#stories .results a");
    for (var i=0;i<links.length;i++) {
        links[i].onclick = function(e) {
            var selected = document.querySelectorAll("#stories .results .newsSelected");
            for (var j=0;j<selected.length;j++) {
                selected[j].className=selected[j].oldclass;
            }
            this.oldclass = this.className;
            this.className += ' newsSelected';
            var httpRequest = new XMLHttpRequest();
            httpRequest.open("GET", this.href+'&ajax=1', true);
            httpRequest.onreadystatechange = function() {
                if (httpRequest.readyState == 4 && httpRequest.status == 200) {
                    document.getElementById('storyDetail').innerHTML = httpRequest.responseText;
                    newsDetailScroller.scrollTo(0,0);
                    newsDetailScroller.refresh();
                    moduleHandleWindowResize();
                }
            }
            httpRequest.send(null);
            if (e) {
                e.preventDefault();
            }
        }
        if (i==0) {
            links[i].onclick();
        }
    }

    moduleHandleWindowResize();
    
}

function moduleHandleWindowResize() {
    document.getElementById('stories').style.height = 'auto';
    document.getElementById('storyDetailWrapper').style.height = 'auto';
	var wrapperHeight = document.getElementById('wrapper').offsetHeight;
    var headerHeight = document.getElementById('newsHeader').offsetHeight;
    var contentHeight = wrapperHeight - headerHeight;
	document.getElementById('stories').style.height = contentHeight + 'px';
	document.getElementById('storyDetailWrapper').style.height = contentHeight + 'px';
}
