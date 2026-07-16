(() => {
  "use strict";

  if (window.SCLibraryDiscoveryInterfaceReliabilityLoaded) {
    return;
  }
  window.SCLibraryDiscoveryInterfaceReliabilityLoaded = true;

  const LIVE_ROOT_SELECTOR = ".sc-library[data-sc-library]";
  const MANUAL_ROOT_SELECTOR = ".cc-rl-manual-topic-map";
  const SUMMARY_SELECTOR =
    `${LIVE_ROOT_SELECTOR} details > summary, ${MANUAL_ROOT_SELECTOR} details > summary`;
  const AMP_ENTITY_PATTERN = /&(?:amp|#0*38|#x0*26);/i;
  let repairQueued = false;
  let normalizing = false;

  const decodeAmpersands = (value) => {
    let current = String(value ?? "");

    for (let pass = 0; pass < 5; pass += 1) {
      const decoded = current.replace(/&(?:amp|#0*38|#x0*26);/gi, "&");

      if (decoded === current) {
        break;
      }

      current = decoded;
    }

    return current;
  };

  const normalizeRoot = (root) => {
    if (!(root instanceof Element)) {
      return;
    }

    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        const parent = node.parentElement;

        if (!parent || parent.closest("script, style, textarea, code, pre")) {
          return NodeFilter.FILTER_REJECT;
        }

        return AMP_ENTITY_PATTERN.test(node.nodeValue || "")
          ? NodeFilter.FILTER_ACCEPT
          : NodeFilter.FILTER_REJECT;
      },
    });

    const textNodes = [];
    let node;

    while ((node = walker.nextNode())) {
      textNodes.push(node);
    }

    textNodes.forEach((textNode) => {
      const current = textNode.nodeValue || "";
      const decoded = decodeAmpersands(current);

      if (decoded !== current) {
        textNode.nodeValue = decoded;
      }
    });

    root.querySelectorAll("[title], [aria-label]").forEach((element) => {
      ["title", "aria-label"].forEach((attribute) => {
        if (!element.hasAttribute(attribute)) {
          return;
        }

        const current = element.getAttribute(attribute) || "";
        const decoded = decodeAmpersands(current);

        if (decoded !== current) {
          element.setAttribute(attribute, decoded);
        }
      });
    });
  };



  const repairDiscoveryNote = () => {
    document.querySelectorAll(".cc-research-library-brand.cc-rl-v2 .cc-rl-discovery-note").forEach((note) => {
      if (!(note instanceof HTMLElement)) {
        return;
      }

      let content = note.querySelector(":scope > .cc-rl-discovery-note__content");

      // Older saved page HTML may contain the heading and paragraph as direct
      // children. Normalize that markup in the browser instead of requiring the
      // page editor to preserve a new wrapper.
      if (!(content instanceof HTMLElement)) {
        content = document.createElement("div");
        content.className = "cc-rl-discovery-note__content";

        const children = Array.from(note.childNodes);
        children.forEach((child) => content.appendChild(child));
        note.appendChild(content);
      }

      note.style.setProperty("display", "block", "important");
      note.style.setProperty("width", "100%", "important");
      note.style.setProperty("min-height", "0", "important");
      note.style.setProperty("height", "auto", "important");
      note.style.setProperty("padding", "14px 18px", "important");
      note.style.setProperty("box-sizing", "border-box", "important");

      const mobile = window.matchMedia("(max-width: 760px)").matches;
      content.style.setProperty("display", "grid", "important");
      content.style.setProperty(
        "grid-template-columns",
        mobile ? "1fr" : "minmax(185px, 235px) minmax(0, 1fr)",
        "important"
      );
      content.style.setProperty("align-items", "start", "important");
      content.style.setProperty("gap", mobile ? "6px" : "8px 22px", "important");
      content.style.setProperty("width", "100%", "important");
      content.style.setProperty("max-width", "none", "important");
      content.style.setProperty("min-width", "0", "important");
      content.style.setProperty("margin", "0", "important");
      content.style.setProperty("padding", "0", "important");

      content.querySelectorAll(":scope > strong, :scope > p").forEach((element) => {
        if (!(element instanceof HTMLElement)) {
          return;
        }

        element.style.setProperty("display", "block", "important");
        element.style.setProperty("width", "auto", "important");
        element.style.setProperty("max-width", "none", "important");
        element.style.setProperty("min-width", "0", "important");
        element.style.setProperty("height", "auto", "important");
        element.style.setProperty("margin", "0", "important");
        element.style.setProperty("padding", "0", "important");
        element.style.setProperty("writing-mode", "horizontal-tb", "important");
        element.style.setProperty("white-space", "normal", "important");
        element.style.setProperty("word-break", "normal", "important");
        element.style.setProperty("overflow-wrap", "normal", "important");
      });
    });
  };

  const syncDetailsState = (details) => {
    if (!(details instanceof HTMLDetailsElement)) {
      return;
    }

    const summary = details.querySelector(":scope > summary");

    if (!(summary instanceof HTMLElement)) {
      return;
    }

    const isOpen = details.open;
    summary.setAttribute("aria-expanded", isOpen ? "true" : "false");
    details.classList.toggle("is-sc-library-repair-open", isOpen);

    const toggle = summary.querySelector(
      ".sc-library-domain__toggle, .sc-library__toggle, [data-topic-toggle-icon]"
    );

    if (toggle instanceof HTMLElement) {
      toggle.textContent = isOpen ? "−" : "+";
      toggle.setAttribute("aria-hidden", "true");
    }
  };

  const findControlledSummary = (target) => {
    if (!(target instanceof Element)) {
      return null;
    }

    const summary = target.closest("summary");

    if (!(summary instanceof HTMLElement) || !summary.matches(SUMMARY_SELECTOR)) {
      return null;
    }

    return summary;
  };

  const toggleSummary = (summary, event) => {
    const details = summary.parentElement;

    if (!(details instanceof HTMLDetailsElement)) {
      return;
    }

    event.preventDefault();
    event.stopPropagation();
    event.stopImmediatePropagation();

    details.open = !details.open;
    syncDetailsState(details);

    details.dispatchEvent(
      new CustomEvent("sc-library-discovery-interface-reliability:toggle", {
        bubbles: true,
        detail: { open: details.open },
      })
    );
  };

  const handleClick = (event) => {
    const summary = findControlledSummary(event.target);

    if (summary) {
      toggleSummary(summary, event);
    }
  };

  const repair = () => {
    if (normalizing) {
      return;
    }

    normalizing = true;

    try {
      repairDiscoveryNote();

      document
        .querySelectorAll(`${LIVE_ROOT_SELECTOR}, ${MANUAL_ROOT_SELECTOR}`)
        .forEach((root) => {
          normalizeRoot(root);
          root.querySelectorAll("details").forEach(syncDetailsState);
        });
    } finally {
      normalizing = false;
    }
  };

  const queueRepair = () => {
    if (repairQueued) {
      return;
    }

    repairQueued = true;

    window.requestAnimationFrame(() => {
      repairQueued = false;
      repair();
    });
  };

  const start = () => {
    repair();

    const observer = new MutationObserver(queueRepair);
    observer.observe(document.body, {
      childList: true,
      subtree: true,
      characterData: true,
    });

    document.addEventListener("toggle", (event) => {
      if (event.target instanceof HTMLDetailsElement) {
        syncDetailsState(event.target);
      }
    }, true);

    document.addEventListener("sc-library-discovery-ready", queueRepair);
    window.addEventListener("pageshow", queueRepair);
    window.addEventListener("resize", queueRepair);
  };

  // Window-level capture runs before document- and component-level handlers,
  // preventing theme or plugin listeners from cancelling the disclosure.
  window.addEventListener("click", handleClick, true);

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", start, { once: true });
  } else {
    start();
  }
})();
