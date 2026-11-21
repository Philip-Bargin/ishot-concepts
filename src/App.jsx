import { Suspense, lazy, useEffect, useRef } from "react";
import { Link, Route, Routes, useLocation } from "react-router-dom";
import Header from "./components/Header.jsx";
import Footer from "./components/Footer.jsx";
import { PageTitle } from "./components/ui.jsx";
import { usePageTitle } from "./lib/hooks.js";
import Home from "./pages/Home.jsx";
// Home ships in the main bundle; every other page loads on demand (keeps first-load JS small).
const About = lazy(() => import("./pages/About.jsx"));
const Services = lazy(() => import("./pages/Services.jsx"));
const Portfolio = lazy(() => import("./pages/Portfolio.jsx"));
const Project = lazy(() => import("./pages/Project.jsx"));
const Creative = lazy(() => import("./pages/Creative.jsx"));
const Journal = lazy(() => import("./pages/Journal.jsx"));
const Article = lazy(() => import("./pages/Article.jsx"));
const Contact = lazy(() => import("./pages/Contact.jsx"));
const Book = lazy(() => import("./pages/Book.jsx"));
const Account = lazy(() => import("./pages/Account.jsx"));
const ManageBooking = lazy(() => import("./pages/ManageBooking.jsx"));

// Scroll to top on navigation, to #hash targets when present, and keep the scroll
// position when only the portfolio category changes (sticky filter chips).
function ScrollManager() {
  const { pathname, hash } = useLocation();
  const previous = useRef(pathname);

  useEffect(() => {
    const was = previous.current;
    previous.current = pathname;
    if (hash) {
      requestAnimationFrame(() => document.getElementById(decodeURIComponent(hash.slice(1)))?.scrollIntoView());
      return;
    }
    if (was.startsWith("/portfolio") && pathname.startsWith("/portfolio") && was !== "/") return;
    window.scrollTo(0, 0);
  }, [pathname, hash]);

  return null;
}

function NotFound() {
  usePageTitle("Page not found");
  return (
    <>
      <PageTitle eyebrow="404" title="Page not found" lead="The page you're looking for has moved or never existed." />
      <section className="fo-section">
        <div className="fo-container ish-actions">
          <Link to="/" className="fo-btn fo-btn--primary">
            Go home
          </Link>
          <Link to="/portfolio" className="fo-btn fo-btn--outline">
            View gallery
          </Link>
        </div>
      </section>
    </>
  );
}

export default function App() {
  return (
    <div className="fo-root ish-app">
      <a href="#main" className="ish-skip">
        Skip to content
      </a>
      <ScrollManager />
      <Header />
      <main id="main" tabIndex={-1}>
        <Suspense fallback={<div className="fo-section ish-loading" aria-busy="true" />}>
        <Routes>
          <Route path="/" element={<Home />} />
          <Route path="/about" element={<About />} />
          <Route path="/services" element={<Services />} />
          <Route path="/portfolio" element={<Portfolio />} />
          <Route path="/portfolio/:category" element={<Portfolio />} />
          <Route path="/work/:slug" element={<Project />} />
          <Route path="/creative-direction" element={<Creative />} />
          <Route path="/journal" element={<Journal />} />
          <Route path="/journal/:slug" element={<Article />} />
          <Route path="/contact" element={<Contact />} />
          <Route path="/book" element={<Book />} />
          <Route path="/account" element={<Account />} />
          <Route path="/manage/:token" element={<ManageBooking />} />
          <Route path="*" element={<NotFound />} />
        </Routes>
        </Suspense>
      </main>
      <Footer />
    </div>
  );
}
