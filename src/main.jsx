import React from "react";
import ReactDOM from "react-dom/client";
import { BrowserRouter } from "react-router-dom";

// Self-hosted brand fonts (no Google Fonts requests).
import "@fontsource/oswald/500.css";
import "@fontsource/oswald/600.css";
import "@fontsource/oswald/700.css";
import "@fontsource/space-grotesk/400.css";
import "@fontsource/space-grotesk/500.css";
import "@fontsource/space-grotesk/700.css";

// Design system (generated from design-system/ by `npm run sync:ds`), then the app layer.
import "./styles/ds/tokens.css";
import "./styles/ds/bundle.css";
import "./index.css";

import { getInitialTheme } from "./lib/theme.js";
import App from "./App.jsx";

document.documentElement.dataset.theme = getInitialTheme();

ReactDOM.createRoot(document.getElementById("root")).render(
  <React.StrictMode>
    <BrowserRouter future={{ v7_startTransition: true, v7_relativeSplatPath: true }}>
      <App />
    </BrowserRouter>
  </React.StrictMode>
);
