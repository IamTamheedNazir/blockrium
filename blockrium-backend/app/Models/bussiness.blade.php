@include('frontend.include.header')
<section class="contact_us">
  <div class="banner_contact_us businss_bnr">
     <span class="contct_page"> <a href="index.php">Home</a> / <h2>Bussiness</h2></span>
  </div>
</section>


  <section class="our_bussiness">
    <div class="container">
      <h1>Our Bussiness</h1>
      <div class="row">
        <div class="col-md-4 col-12">
          <div class="inr_businss">
            <div class="buss_img">
              <img src="{{asset('images/buisins_one.jpg')}}" alt="images">
            </div>
            <h2>O2G Capital</h2>
            <p>Office ManagerO2G SECURED CAPITAL INC. (O2G) A Canadian Mortgage Investment Corporation (MIC) fully authorized to pool investors’ money into an O2G fund to invest / underwrite mortgages.</p>
            <a href="http://o2g.idea-ads.com/">Read More</a>
          </div>
        </div>
        <div class="col-md-4 col-12">
          <div class="inr_businss">
            <div class="buss_img">
              <img src="images/buisins_two.jpg" alt="images">
            </div>
            <h2>Peace Environmental</h2>
            <p>PEACE Environmental has been an integral part of the Environmental community of Ontario since 2007. PEACE Environmental is your one-stop-shop for all environmental.</p>
            <a href="http://www.peaceinc.ca/">Read More</a>
          </div>
        </div>
        <div class="col-md-4 col-12">
          <div class="inr_businss">
            <div class="buss_img">
              <img src="images/buisins_three.jpg" alt="images">
            </div>
            <h2>Bliss Mortgages</h2>
            <p>Bliss Mortgages Inc. is fully licensed and approved, as Mortgage Administrator, by the Financial Services Commission of Ontario (FSCO), License # 12747.</p>
            <a href="http://www.blissmortgages.com/">Read More</a>
          </div>
        </div>
        <div class="col-md-4 col-12">
          <div class="inr_businss">
            <div class="buss_img">
              <img src="images/buisins_four.jpg" alt="images">
            </div>
            <h2>O2G Mortgages</h2>
            <p>O2G Mortgages Sources Inc. is fully licensed and approved, as Mortgage Brokerage, by the Financial Services Commission of Ontario (FSCO), License # 12792.</p>
            <a href="http://www.o2gmortgages.com/">Read More</a>
          </div>
        </div>
        <div class="col-md-4 col-12">
          <div class="inr_businss">
            <div class="buss_img">
              <img src="images/buisins_five.jpg" alt="images">
            </div>
            <h2>Canadian Falco (Township Development)</h2>
            <p>PMC Falco Property Management – At PMC Falco our primary objective is to provide a professional holistic solution to commercial property management services. </p>
            <a href="http://o2g.idea-ads.com/">Read More</a>
          </div>
        </div>  
        <div class="col-md-4 col-12">
          <div class="inr_businss">
            <div class="buss_img">
              <img src="images/buisins_six.jpg" alt="images">
            </div>
            <h2>PMC FALCO</h2>
            <p>At PMC Falco, our primary objective is to provide a professional holistic solution to commercial property management services. We utilize our extensive knowledge.</p>
            <a href="http://o2g.idea-ads.com/">Read More</a> 
          </div>
        </div>  
      </div>
    </div>
  
  </section>




@include('frontend.include.footer')
  
<script src="js/floating-totop-button.js"></script>
<script>
  var TxtType = function(el, toRotate, period) {
        this.toRotate = toRotate;
        this.el = el;
        this.loopNum = 0;
        this.period = parseInt(period, 10) || 2000;
        this.txt = '';
        this.tick();
        this.isDeleting = false;
    };

    TxtType.prototype.tick = function() {
        var i = this.loopNum % this.toRotate.length;
        var fullTxt = this.toRotate[i];

        if (this.isDeleting) {
        this.txt = fullTxt.substring(0, this.txt.length - 1);
        } else {
        this.txt = fullTxt.substring(0, this.txt.length + 1);
        }

        this.el.innerHTML = '<span class="wrap">'+this.txt+'</span>';

        var that = this;
        var delta = 200 - Math.random() * 100;

        if (this.isDeleting) { delta /= 2; }

        if (!this.isDeleting && this.txt === fullTxt) {
        delta = this.period;
        this.isDeleting = true;
        } else if (this.isDeleting && this.txt === '') {
        this.isDeleting = false;
        this.loopNum++;
        delta = 500;
        }

        setTimeout(function() {
        that.tick();
        }, delta);
    };

    window.onload = function() {
        var elements = document.getElementsByClassName('typewrite');
        for (var i=0; i<elements.length; i++) {
            var toRotate = elements[i].getAttribute('data-type');
            var period = elements[i].getAttribute('data-period');
            if (toRotate) {
              new TxtType(elements[i], JSON.parse(toRotate), period);
            }
        }
        // INJECT CSS
        var css = document.createElement("style");
        css.type = "text/css";
        css.innerHTML = ".typewrite > .wrap { border-right: 0.08em solid #fff}";
        document.body.appendChild(css);
    };
</script>
<script>
  /*Downloaded from https://www.codeseek.co/ezra_siton/mixitup-fancybox3-JydYqm */
  // 1. querySelector
  var containerEl = document.querySelector(".portfolio-item");
  // 2. Passing the configuration object inline
  //https://www.kunkalabs.com/mixitup/docs/configuration-object/
  var mixer = mixitup(containerEl, {
    animation: {
      effects: "fade translateZ(-100px)",
      effectsIn: "fade translateY(-100%)",
      easing: "cubic-bezier(0.645, 0.045, 0.355, 1)"
    }
  });
  // fancybox insilaze & options //
  $("[data-fancybox]").fancybox({
    loop: true,
    hash: true,
    transitionEffect: "slide",
    /* zoom VS next////////////////////
    clickContent - i modify the deafult - now when you click on the image you go to the next image - i more like this approach than zoom on desktop (This idea was in the classic/first lightbox) */
    clickContent: function(current, event) {
      return current.type === "image" ? "next" : false;
    }
  });
</script>

<script type="text/javascript">
$("body").toTopButton({});
  var _gaq = _gaq || [];
  _gaq.push(['_setAccount', 'UA-36251023-1']);
  _gaq.push(['_setDomainName', 'jqueryscript.net']);
  _gaq.push(['_trackPageview']);

  (function() {
    var ga = document.createElement('script'); ga.type = 'text/javascript'; ga.async = true;
    ga.src = ('https:' == document.location.protocol ? 'https://ssl' : 'http://www') + '.google-analytics.com/ga.js';
    var s = document.getElementsByTagName('script')[0]; s.parentNode.insertBefore(ga, s);
  })();

</script>
</html>