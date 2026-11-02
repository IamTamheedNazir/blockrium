<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"/>
    <link rel="stylesheet" href="https://unpkg.com/aos@next/dist/aos.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/slick-carousel@1.8.1/slick/slick.css">
    <link rel="stylesheet" href="{{asset('/frontend_new/css/style.css')}}">
    <link rel="shortcut icon" href="{{asset('/frontend_new/images/brc_favicon.png')}}">
    <title>Blockrium Network</title>
</head>
<body>
<!-- Main Top BG Start -->
<div class="main-color-wrapper position-relative" > 
    <!-- Nav-bar Start -->
        <nav class="navbar navbar-expand-lg p-2">
            <div class="container">
            <a class="navbar-brand" href="#"><img src="{{asset('/frontend_new/images/main logo_full 1.png')}}" alt="image"></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse justify-content-end" id="navbarNav">
                <ul class="navbar-nav">
                <li class="nav-item ">
                    <a class="nav-link active" aria-current="page" href="/">Home</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#overview">Overview</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#hub">Features</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#roadmap">Roadmap</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#">Showcase</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="#teams">Team</a>
                </li>
                </ul>
            </div>
            </div>
        </nav>
    <!-- Nav-bar End -->
    <!-- Banner Section Start -->
    <section id="banner" class="p-5">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-xl-6" data-aos="fade-right">
                    <div class="left-banner-wrapper">
                        <h1 class="mb-2">Next Evolution Crypto Exchange built on Blockrium</h1>
                        <p class="mb-2">Pioneering the Ultimate Blockrium DeFi Ecosystem </p>
                        <div class="d-flex flex-wrap playstore gap-4 align-items-center pt-5">
                            <a href="https://play.google.com/store/apps/details?id=com.maticlegend.web3"><img src="{{asset('/frontend_new/images/google-play.png')}}" alt="image"></a>
                            <a href="#"><img src="{{asset('/frontend_new/images/apple-app-store.png')}}" alt="image"></a>
                        </div>
                    </div>
                </div>
                <div class="col-xl-6 text-center" data-aos="fade-left">
                    <img src="{{asset('/frontend_new/images/banner-img.png')}}" class="w-100 bounce-img" alt="image">
                </div>
            </div>
        </div>
           
    </section>
    <!-- Banner Section End -->
    <img src="{{asset('/frontend_new/images/arrow.png')}}"  class="right-arrow" alt="image">
</div>
<!-- Main Top BG End-->

<!-- Overview Section Start -->
<section id="overview" class="p-sm-5 p-3 position-relative">
<div class="container">
    <div class="row align-items-center">
        <div class="col-xl-6 text-center" data-aos="fade-right">
            <img src="{{asset('/frontend_new/images/icon-gold.png')}}" alt="image" class="w-auto">
        </div>
        <div class="col-xl-6" data-aos="fade-left">
            <div class="overview-wrapper">
                <h1 class="mb-4">Overview</h1>
                <p>Blockrium is a decentralized exchange (DEX) and an automated market maker (AMM) built on the Blockrium blockchain to facilitate trades for users to exchange native BRC tokens and also allows participants of the blockchain to MineBRC tokens in a completely decentralized ecosystem.</p>
                <p>By building on Blockrium Blockchain, It will be the first project on the BRC Ecosystem utilizing its smart contracts to build a complete suite of DeFi services on the Blockrium Blockchain including a Decentralized Exchange, NFT Marketplace, Launchpad and a Lending Protocol for native BRC tokens. </p>
            </div>
        </div>
    </div>
</div>
<img src="{{asset('/frontend_new/images/arrow.png')}}"  class="right-arrow-overview" alt="image">
</section>
<!-- Overview Section End -->

<!-- Hub Section Start -->

<section id="hub" class="p-sm-5 p-3 position-relative">
    <div class="container">
        <h1 class="hub-heading my-2 py-5">A Hub Ensuite With DeFi Services</h1>
        <div class="row mt-2 mb-5">
            <div class="col-lg-4 my-3" data-aos="zoom-in">
                <div class="card-wrapper">
                    <img src="{{asset('/frontend_new/images/pile of yellow coins.png')}}" alt="image">
                    <div class="card-white-bg">
                        <h1>Decentralized Exchange</h1>
                        <p>An AMM-based DEX offering a list of services to it's users such as Token Swap, Liquidity pool, Staking protocol with the purpose of making this DEX a one stop swap trading exchange for our user’s convenience and effortless trading experience.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 my-3" data-aos="zoom-in">
                <div class="card-wrapper">
                    <img src="{{asset('/frontend_new/images/Buying presents.png')}}" alt="image">
                    <div class="card-white-bg">
                        <h1>NFT Marketplace</h1>
                        <p>An AMM-based DEX offering a list of services to it's users such as Token Swap, Liquidity pool, Staking protocol with the purpose of making this DEX a one stop swap trading exchange for our user’s convenience and effortless trading experience.
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-lg-4 my-3" data-aos="zoom-in">
                <div class="card-wrapper">
                    <img src="{{asset('/frontend_new/images/team work sticker.png')}}" alt="image">
                    <div class="card-white-bg">
                        <h1>Launchpad</h1>
                        <p>An AMM-based DEX offering a list of services to it's users such as Token Swap, Liquidity pool, Staking protocol with the purpose of making this DEX a one stop swap trading exchange for our user’s convenience and effortless trading experience.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- Hub Section End -->
<!-- Roadmap Section Start -->
<div class="main-roadmap">
<section id="roadmap" class="p-sm-5 p-3 position-relative">
    <div class="container">
        <h1 class="roadmap-heading my-2 py-5">Roadmap</h1>
        <div class="main-road-wrapper position-relative">
        <div class="row mt-2 mb-5">
            <div class="col-lg-3 my-3" data-aos="zoom-out">
                <div class="roadmap-cart center text-center position-relative">
                    <div class="heading-roadmap">
                        <h1>Q2 2022</h1>
                    </div>
                    <ul class="list-unstyled">
                        <li class="p-1">Idea Generation </li>
                        <li class="p-1">Market Research</li>
                        <li class="p-1">XertzSwap Project Announcement </li>
                        <li class="p-1">Marketing and Brand Awareness  </li>
                        <li class="p-1">Deploying of XEZ Token on Cardano Blockchain </li>
                        <li class="p-1">Seed Sale</li>
                        <li class="p-1">UI Design of XertzSwap AMM/DEX </li>
                    </ul>      
                </div>
            </div>
            <div class="col-lg-3"></div>
            <div class="col-lg-3 my-3" data-aos="zoom-out">
                <div class="roadmap-cart center text-center position-relative">
                    <div class="heading-roadmap">
                        <h1>Q2 2022</h1>
                    </div>
                    <ul class="list-unstyled">
                        <li class="p-1">Idea Generation </li>
                        <li class="p-1">Market Research</li>
                        <li class="p-1">XertzSwap Project Announcement </li>
                        <li class="p-1">Marketing and Brand Awareness  </li>
                        <li class="p-1">Deploying of XEZ Token on Cardano Blockchain </li>
                        <li class="p-1">Seed Sale</li>
                        <li class="p-1">UI Design of XertzSwap AMM/DEX </li>
                    </ul>      
                </div>
            </div>
            <div class="col-lg-3"></div>
            </div>
            <div class="road-wrapper"></div>
            <div class="row my-5">
            <div class="col-lg-3"></div>
            <div class="col-lg-3 my-3" data-aos="zoom-out">
                <div class="roadmap-cart bottom-r center text-center position-relative">
                    <div class="heading-roadmap">
                        <h1>Q2 2022</h1>
                    </div>
                    <ul class="list-unstyled">
                        <li class="p-1">Idea Generation </li>
                        <li class="p-1">Market Research</li>
                        <li class="p-1">XertzSwap Project Announcement </li>
                        <li class="p-1">Marketing and Brand Awareness  </li>
                        <li class="p-1">Deploying of XEZ Token on Cardano Blockchain </li>
                        <li class="p-1">Seed Sale</li>
                        <li class="p-1">UI Design of XertzSwap AMM/DEX </li>
                    </ul>      
                </div>
            </div>
            <div class="col-lg-3"></div>
            <div class="col-lg-3 my-3" data-aos="zoom-out">
                <div class="roadmap-cart bottom-r center text-center position-relative">
                    <div class="heading-roadmap">
                        <h1>Q2 2022</h1>
                    </div>
                    <ul class="list-unstyled">
                        <li class="p-1">Idea Generation </li>
                        <li class="p-1">Market Research</li>
                        <li class="p-1">XertzSwap Project Announcement </li>
                        <li class="p-1">Marketing and Brand Awareness  </li>
                        <li class="p-1">Deploying of XEZ Token on Cardano Blockchain </li>
                        <li class="p-1">Seed Sale</li>
                        <li class="p-1">UI Design of XertzSwap AMM/DEX </li>
                    </ul>      
                </div>
            </div>
        </div>
        </div>
    </div>
    <img src="{{asset('/frontend_new/images/arrow.png')}}"  class="roadmap-arrow" alt="image">
</section>
<!-- Roadmap Section End -->
<!-- Team Section Start -->
<section id="teams" class="pb-sm-5 p-3 position-relative">
    <div class="container">
        <h1 class="hub-heading my-2 py-5">Teams</h1>
        <div class="row">
            <div class="col-lg-3 my-3 col-md-6" data-aos="flip-left">
                <div class="team-cart">
                   <img src="{{asset('/frontend_new/images/image 1 (1).png')}}" alt="image"> 
                   <div class="team-bg-white">
                    <h1>Tamheed Nazir</h1>
                    <p> Blockchain Developer</p>
                    <div class="d-flex justify-content-center align-items-center gap-3 my-2">
                        <a href="#"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#"><i class="fa-brands fa-square-twitter"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                        <a href="#"><i class="fa-brands fa-telegram"></i></a>
                    </div>
                   </div>
                </div>
            </div>
            <div class="col-lg-3 my-3 col-md-6" data-aos="flip-left">
                <div class="team-cart">
                    <img src="{{asset('/frontend_new/images/image 2.png')}}" alt="image"> 
                    <div class="team-bg-white">
                     <h1>Yash</h1>
                     <p> App Developer </p>
                     <div class="d-flex justify-content-center align-items-center gap-3 my-2">
                        <a href="#"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#"><i class="fa-brands fa-square-twitter"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                        <a href="#"><i class="fa-brands fa-telegram"></i></a>
                    </div>
                    </div>
                 </div>
            </div>
            <div class="col-lg-3 my-3 col-md-6" data-aos="flip-left">
                <div class="team-cart">
                    <img src="{{asset('/frontend_new/images/Image 1.png')}}" alt="image"> 
                    <div class="team-bg-white">
                     <h1>Sahnza Zubair</h1>
                     <p> UI/UX Developer</p>
                     <div class="d-flex justify-content-center align-items-center gap-3 my-2">
                        <a href="#"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#"><i class="fa-brands fa-square-twitter"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                        <a href="#"><i class="fa-brands fa-telegram"></i></a>
                    </div>
                    </div>
                 </div>
            </div>
            <div class="col-lg-3 my-3 col-md-6" data-aos="flip-left">
                <div class="team-cart">
                    <img src="{{asset('/frontend_new/images/image 3.png')}}" alt="image"> 
                    <div class="team-bg-white">
                     <h1>Keshab Chandra Mandal</h1>
                     <p> Fullstack/Server Developer</p>
                     <div class="d-flex justify-content-center align-items-center gap-3 my-2">
                        <a href="#"><i class="fa-brands fa-facebook"></i></a>
                        <a href="#"><i class="fa-brands fa-square-twitter"></i></a>
                        <a href="#"><i class="fa-brands fa-linkedin"></i></a>
                        <a href="#"><i class="fa-brands fa-telegram"></i></a>
                    </div>
                    </div>
                 </div>
            </div>
        </div>
    </div>
</section>
</div>
<!-- Team Section End -->

<!-- Press Release SEction Start -->
<Section id="press" class="p-sm-5 p-3 position-relative">
<div class="container">
    <h1 class="hub-heading my-2 py-5">Press Release</h1>
    <div class="slick-slider my-5">
        <div class="element element-1"><img src="{{asset('/frontend_new/images/cointelegraph-logo-vector 1.png')}}" alt=" image"></div>
        <div class="element element-2"><img src="{{asset('/frontend_new/images/investing 1.png')}}" alt="image"></div>
        <div class="element element-3"><img src="{{asset('/frontend_new/images/whitelogo.png')}}" alt="image"></div>
        <div class="element element-4"><img src="{{asset('/frontend_new/images/newbtc 1.png')}}" alt="image"></div>
        <div class="element element-5"><img src="{{asset('/frontend_new/images/yahoo-finance-logo-D576345001-seeklogo 1.png')}}" alt="image"></div>
        <div class="element element-1"><img src="{{asset('/frontend_new/images/cointelegraph-logo-vector 1.png')}}" alt=" image"></div>
        <div class="element element-2"><img src="{{asset('/frontend_new/images/investing 1.png')}}" alt="image"></div>
        <div class="element element-3"><img src="{{asset('/frontend_new/images/whitelogo.png')}}" alt="image"></div>
        <div class="element element-4"><img src="{{asset('/frontend_new/images/newbtc 1.png')}}" alt="image"></div>
        <div class="element element-5"><img src="{{asset('/frontend_new/images/yahoo-finance-logo-D576345001-seeklogo 1.png')}}" alt="image"></div>
      </div>
      <img src="{{asset('/frontend_new/images/Icon 2.png')}}" class="press-arrow" alt="img">
</div>
</Section>
<!-- Press Release SEction End -->
<!-- Footer Start -->
<footer class="pt-sm-5 pt-3 position-relative">
<div class="container">
    <div class="row my-5">
        <div class="col-md-3">
            <a href="#"><img src="{{asset('/frontend_new/images/main logo_full 1.png')}}" alt="" class="logo-footer"></a>
        </div>
        <div class="col-md-6"></div>
        <div class="col-md-3">
            <div class="footoer-wrapper-icon d-flex align-items-center justify-content-end gap-4">
            <img src="{{asset('/frontend_new/images/whitelogo.png')}}" class="medium-logo" alt="image">
                <div class="icons">
                    <a href="https://t.me/blockrium"><img src="{{asset('/frontend_new/images/teleicon.png')}}" alt="image"></a>
                    <a href="https://twitter.com/Blockrium"><img src="{{asset('/frontend_new/images/twitter 1.png')}}" alt="image"></a>
                    <a href="https://www.linkedin.com/company/blockrium/"><img src="{{asset('/frontend_new/images/simple-icons_gitbook.png')}}" alt="image"></a>
                </div>
            </div>
            <ul class="list-unstyled">
                <li><a href="#">Contact</a></li>
                <li><a href="#">FAQ</a></li>
                <li><a href="{{asset('/whitepaper_doc/Here is the extended white paper for Blockrium.pdf')}}">WhitePaper</a></li>
                <li><a href="#">Partnership and integrations</a></li>
            </ul>
        </div>
    </div>
</div>
<p class=" terms-condition m-0 p-3">© 2022 Blockrium Web3 Technologies | All Rights Reserved</p>
</footer>

<!-- Footer End -->
    
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/jquery.slick/1.4.1/slick.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://unpkg.com/aos@next/dist/aos.js"></script>
<script>
      $(".slick-slider").slick({
   slidesToShow: 4,
   infinite:false,
   slidesToScroll: 2,
   autoplay: true,
   autoplaySpeed: 2000,
   prevArrow: false,
    nextArrow: false,
    responsive: [
{
  breakpoint: 1024,
  settings: {
    slidesToShow: 3,
    slidesToScroll: 3,
  }
},
{
    breakpoint: 992,
    settings: {
      slidesToShow: 2,
      slidesToScroll: 2,
    }
  },
{
  breakpoint: 600, 
  settings: {
    slidesToShow: 1,
    slidesToScroll: 2
  }
},
]
});
AOS.init();
</script>
</html>