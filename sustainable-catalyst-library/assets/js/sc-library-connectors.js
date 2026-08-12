(function () {
  'use strict';

  var config = window.SCLibraryConnectors || {};
  var strings = config.strings || {};

  function make(tag, className, text) {
    var node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (typeof text === 'string') {
      node.textContent = text;
    }
    return node;
  }

  function request(action, values) {
    var body = new URLSearchParams();
    body.set('action', action);
    body.set('nonce', config.nonce || '');
    Object.keys(values || {}).forEach(function (key) {
      var value = values[key];
      if (Array.isArray(value)) {
        value.forEach(function (entry) {
          body.append(key + '[]', entry);
        });
      } else if (value !== undefined && value !== null) {
        body.set(key, value);
      }
    });

    return fetch(config.ajaxUrl, {
      method: 'POST',
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: body.toString()
    }).then(function (response) {
      return response.json().then(function (payload) {
        if (!response.ok || !payload || !payload.success) {
          var data = payload && payload.data ? payload.data : {};
          throw new Error(data.message || strings.failed || 'Request failed.');
        }
        return payload.data;
      });
    });
  }


  function accessLabel(status) {
    var labels = {
      'public-digital': 'Open digital access',
      'open-access': 'Open access',
      'public-catalog': 'Public catalog record',
      'library-holding': 'Library access',
      'preview-only': 'Preview available',
      'institutional-auth': 'Institution login required',
      'physical': 'Physical holding',
      'metadata-only': 'Metadata only'
    };
    return labels[status] || String(status || '').replace(/-/g, ' ');
  }

  function resultAuthors(result) {
    if (result.authors && result.authors.length) {
      return result.authors.map(function (author) {
        return [author.given || '', author.family || ''].filter(Boolean).join(' ');
      }).join(', ');
    }
    return result.organization || '';
  }

  function addLink(container, label, url, className) {
    if (!url) {
      return;
    }
    var link = make('a', className || '', label);
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener';
    container.appendChild(link);
  }

  function replaceLibraryTokens(template, result) {
    var author = resultAuthors(result).split(',')[0] || '';
    var values = {
      '{query}': [result.title || '', author].filter(Boolean).join(' '),
      '{title}': result.title || '',
      '{author}': author,
      '{doi}': result.doi || '',
      '{isbn}': result.isbn || '',
      '{pmid}': result.pmid || ''
    };
    var url = String(template || '');
    Object.keys(values).forEach(function (token) {
      url = url.split(token).join(encodeURIComponent(values[token]));
    });
    return url;
  }

  function bestAccessRoute(result) {
    if (result.open_access_url) {
      return { label: result.full_text_status === 'public-digital' ? 'Open digital copy' : 'Open-access copy', url: result.open_access_url, status: 'open' };
    }
    var libraries = Array.isArray(config.myLibraries) ? config.myLibraries : [];
    var member = libraries.find(function (library) { return library.relation === 'member' && library.catalog_template; });
    if (member) {
      return { label: 'Check ' + member.name, url: replaceLibraryTokens(member.catalog_template, result), status: 'library' };
    }
    var research = libraries.find(function (library) { return library.catalog_template; });
    if (research) {
      return { label: 'Search ' + research.name, url: replaceLibraryTokens(research.catalog_template, result), status: 'library' };
    }
    var worldcat = (result.discovery_links || []).find(function (link) { return link.provider === 'worldcat'; });
    if (worldcat) {
      return { label: 'Find in libraries worldwide', url: worldcat.url, status: 'global' };
    }
    return null;
  }

  function renderAccessResolver(result) {
    var route = bestAccessRoute(result);
    var libraries = Array.isArray(config.myLibraries) ? config.myLibraries.filter(function (library) { return library.catalog_template; }) : [];
    if (!route && !libraries.length) { return null; }
    var panel = make('div', 'sc-connector-result-card__resolver');
    panel.appendChild(make('small', '', 'Best legitimate access route'));
    if (route) {
      addLink(panel, route.label + ' →', route.url, 'sc-access-resolver__best is-' + route.status);
    }
    if (libraries.length) {
      var checks = make('div', 'sc-access-resolver__libraries');
      checks.appendChild(make('span', '', 'Check My Libraries:'));
      libraries.slice(0, 6).forEach(function (library) {
        addLink(checks, library.name, replaceLibraryTokens(library.catalog_template, result));
      });
      panel.appendChild(checks);
    }
    return panel;
  }

  function renderResult(result) {
    var article = make('article', 'sc-connector-result-card');
    article.dataset.provider = result.provider || '';

    var header = make('header', 'sc-connector-result-card__header');
    header.appendChild(make('span', 'sc-connector-result-card__provider', result.provider_name || result.provider || 'Provider'));
    if (result.full_text_status) {
      header.appendChild(make('strong', 'sc-connector-result-card__access', accessLabel(result.full_text_status)));
    }
    article.appendChild(header);

    var title = make('h3', '', result.title || 'Untitled source');
    article.appendChild(title);

    var creator = resultAuthors(result);
    var byline = [creator, result.year || '', result.container_title || ''].filter(Boolean).join(' · ');
    if (byline) {
      article.appendChild(make('p', 'sc-connector-result-card__byline', byline));
    }

    if (result.abstract) {
      var abstract = result.abstract.length > 460 ? result.abstract.slice(0, 457) + '…' : result.abstract;
      article.appendChild(make('p', 'sc-connector-result-card__abstract', abstract));
    }

    var identifiers = make('div', 'sc-connector-result-card__identifiers');
    if (result.doi) {
      identifiers.appendChild(make('span', '', 'DOI: ' + result.doi));
    }
    if (result.isbn) {
      identifiers.appendChild(make('span', '', 'ISBN: ' + result.isbn));
    }
    if (result.pmid) {
      identifiers.appendChild(make('span', '', 'PMID: ' + result.pmid));
    }
    if (result.cited_by_count) {
      identifiers.appendChild(make('span', '', 'Cited by: ' + result.cited_by_count));
    }
    article.appendChild(identifiers);

    if (result.existing_source_ids && result.existing_source_ids.length) {
      article.appendChild(
        make(
          'p',
          'sc-connector-result-card__duplicate',
          result.existing_source_ids.length + ' existing Source record' +
            (result.existing_source_ids.length === 1 ? '' : 's') +
            ' share a persistent identifier.'
        )
      );
    }

    var links = make('div', 'sc-connector-result-card__links');
    addLink(links, 'View source record', result.record_url);
    addLink(links, result.full_text_status === 'public-digital' ? 'Open digital resource' : 'Open access', result.open_access_url, 'is-open-access');
    addLink(links, 'Preview', result.preview_url);
    (result.discovery_links || []).forEach(function (link) {
      addLink(links, link.label || 'Open', link.url);
    });
    article.appendChild(links);

    var resolver = renderAccessResolver(result);
    if (resolver) { article.appendChild(resolver); }

    if ((config.canImport || config.canSavePersonal) && result.import_token) {
      var controls = make('div', 'sc-connector-result-card__controls');
      if (config.canSavePersonal) {
        var saveButton = make('button', 'button', 'Save to My Sources');
        saveButton.type = 'button';
        saveButton.dataset.scSaveSourceToken = result.import_token;
        controls.appendChild(saveButton);
        var personalStatus = make('span', 'sc-connector-result-card__personal-status');
        personalStatus.setAttribute('aria-live', 'polite');
        controls.appendChild(personalStatus);
      }
      if (config.canImport) {
        var importButton = make('button', 'button button-primary', 'Import as Draft Source');
        importButton.type = 'button';
        importButton.dataset.scImportToken = result.import_token;
        controls.appendChild(importButton);
        var status = make('span', 'sc-connector-result-card__import-status');
        status.setAttribute('aria-live', 'polite');
        controls.appendChild(status);
      }
      article.appendChild(controls);
    }

    return article;
  }

  function renderProviderGroup(payload) {
    var section = make('section', 'sc-connector-provider-results');
    var heading = make('div', 'sc-connector-provider-results__heading');
    heading.appendChild(make('h2', '', payload.provider_name || payload.provider));
    heading.appendChild(
      make(
        'span',
        '',
        String(payload.result_count || 0) +
          ' result' +
          ((payload.result_count || 0) === 1 ? '' : 's') +
          (payload.cache_state === 'stale' ? ' · retained stale cache' : (payload.cached ? ' · cached' : ''))
      )
    );
    section.appendChild(heading);

    if (payload.recovery_notice) {
      section.appendChild(make('p', 'sc-connector-recovery-notice', payload.recovery_notice));
    }
    if (payload.live_error) {
      section.appendChild(make('p', 'sc-connector-live-error', payload.live_error));
    }

    if (!payload.results || !payload.results.length) {
      section.appendChild(make('p', 'sc-connector-empty', strings.none || 'No matching records were returned.'));
      return section;
    }

    var list = make('div', 'sc-connector-provider-results__list');
    payload.results.forEach(function (result) {
      list.appendChild(renderResult(result));
    });
    section.appendChild(list);
    return section;
  }


  document.addEventListener('click', function (event) {
    var button = event.target.closest('[data-sc-save-source-token]');
    if (!button) { return; }
    event.preventDefault();
    var card = button.closest('.sc-connector-result-card');
    var status = card ? card.querySelector('.sc-connector-result-card__personal-status') : null;
    button.disabled = true;
    if (status) { status.textContent = 'Saving to My Sources…'; }
    request('sc_library_v4322_save_result', { token: button.dataset.scSaveSourceToken }).then(function (payload) {
      button.textContent = 'Saved';
      document.dispatchEvent(new CustomEvent('sc:citation-source-saved', { detail: payload }));
      if (status) {
        status.textContent = payload.message || 'Saved to My Sources.';
        var link = make('a', 'sc-connector-result-card__studio-link', 'Open Citation Studio →');
        link.href = payload.studio_url || config.citationStudio || '#citation-studio';
        status.appendChild(document.createTextNode(' '));
        status.appendChild(link);
      }
    }).catch(function (error) {
      button.disabled = false;
      if (status) { status.textContent = error.message; }
    });
  });


  document.querySelectorAll('[data-sc-research-access]').forEach(function (root) {
    var form = root.querySelector('[data-sc-research-access-form]');
    var resultsNode = root.querySelector('[data-sc-research-access-results]');
    var statusNode = root.querySelector('[data-sc-research-access-status]');
    var summaryNode = root.querySelector('[data-sc-research-access-summary]');
    var berkeley = root.querySelector('[data-sc-berkeley-handoff]');
    if (!form || !resultsNode) { return; }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var query = String(data.get('query') || '').trim();
      var limit = parseInt(data.get('limit') || '5', 10);
      var providers = Array.prototype.slice.call(root.querySelectorAll('input[name="research_access_providers[]"]:checked')).map(function (input) { return input.value; });
      if (query.length < 2 || !providers.length) {
        if (statusNode) { statusNode.textContent = query.length < 2 ? 'Enter at least two characters.' : 'Select at least one research source.'; }
        return;
      }
      if (berkeley) {
        berkeley.href = String(root.dataset.berkeleySearchTemplate || 'https://escholarship.org/').replace('{query}', encodeURIComponent(query));
      }
      var scholar = root.querySelector('[data-sc-google-scholar-handoff]');
      if (scholar) { scholar.href = String(root.dataset.googleScholarTemplate || 'https://scholar.google.com/scholar?q={query}').replace('{query}', encodeURIComponent(query)); }
      root.querySelectorAll('[data-sc-research-gateway], [data-sc-my-library-search]').forEach(function (link) {
        var template = String(link.dataset.searchTemplate || link.href || '');
        if (template.indexOf('{query}') !== -1) { link.href = template.replace('{query}', encodeURIComponent(query)); }
      });
      resultsNode.innerHTML = '';
      if (summaryNode) { summaryNode.textContent = ''; }
      if (statusNode) { statusNode.textContent = 'Searching ' + providers.length + ' public research sources…'; }
      var completed = 0, totalResults = 0, failures = 0;
      Promise.all(providers.map(function (provider) {
        return request('sc_library_v4317_research_access_search', { provider: provider, query: query, limit: limit })
          .then(function (payload) {
            completed += 1;
            totalResults += parseInt(payload.result_count || 0, 10);
            resultsNode.appendChild(renderProviderGroup(payload));
            if (statusNode) { statusNode.textContent = completed + ' of ' + providers.length + ' sources complete.'; }
            return payload;
          })
          .catch(function (error) {
            completed += 1; failures += 1;
            var section = make('section', 'sc-connector-provider-results is-error');
            section.appendChild(make('h2', '', provider));
            section.appendChild(make('p', '', error.message));
            resultsNode.appendChild(section);
            if (statusNode) { statusNode.textContent = completed + ' of ' + providers.length + ' sources complete.'; }
            return null;
          });
      })).then(function () {
        if (statusNode) { statusNode.textContent = 'Research Access search complete.'; }
        if (summaryNode) {
          summaryNode.textContent = totalResults + ' normalized results across ' + providers.length + ' sources' + (failures ? ' · ' + failures + ' source failures' : '') + '. Open resources are shown with explicit access labels.';
        }
      });
    });
  });


  document.querySelectorAll('[data-sc-my-libraries]').forEach(function (root) {
    var knownForm = root.querySelector('[data-sc-connect-library-form]');
    var customForm = root.querySelector('[data-sc-custom-library-form]');

    function saveLibrary(form, statusNode) {
      var data = new FormData(form);
      if (statusNode) { statusNode.textContent = 'Connecting library…'; }
      var values = {};
      data.forEach(function (value, key) { values[key] = value; });
      return request('sc_library_v4319_save_library', values).then(function (payload) {
        config.myLibraries = payload.libraries || [];
        if (statusNode) { statusNode.textContent = payload.message || 'Library connected.'; }
        window.setTimeout(function () { window.location.reload(); }, 350);
      }).catch(function (error) {
        if (statusNode) { statusNode.textContent = error.message; }
      });
    }

    if (knownForm) {
      knownForm.addEventListener('submit', function (event) {
        event.preventDefault();
        saveLibrary(knownForm, knownForm.querySelector('[data-sc-library-connect-status]'));
      });
    }
    if (customForm) {
      customForm.addEventListener('submit', function (event) {
        event.preventDefault();
        saveLibrary(customForm, customForm.querySelector('[data-sc-custom-library-status]'));
      });
    }
    root.addEventListener('click', function (event) {
      var button = event.target.closest('[data-sc-remove-library]');
      if (!button) { return; }
      event.preventDefault();
      button.disabled = true;
      request('sc_library_v4319_remove_library', { library_id: button.dataset.scRemoveLibrary }).then(function (payload) {
        config.myLibraries = payload.libraries || [];
        var card = button.closest('[data-library-id]');
        if (card) { card.remove(); }
      }).catch(function (error) {
        button.disabled = false;
        button.textContent = error.message;
      });
    });
  });

  document.querySelectorAll('[data-sc-connector-discovery]').forEach(function (root) {
    var form = root.querySelector('[data-sc-connector-search-form]');
    var resultsNode = root.querySelector('[data-sc-connector-results]');
    var statusNode = root.querySelector('[data-sc-connector-status]');
    var summaryNode = root.querySelector('[data-sc-connector-summary]');

    if (!form || !resultsNode) {
      return;
    }

    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var formData = new FormData(form);
      var query = String(formData.get('query') || '').trim();
      var limit = parseInt(formData.get('limit') || '8', 10);
      var providers = Array.prototype.slice
        .call(root.querySelectorAll('input[name="providers[]"]:checked:not(:disabled)'))
        .map(function (input) { return input.value; });

      if (query.length < 2 || !providers.length) {
        if (statusNode) {
          statusNode.textContent = query.length < 2
            ? 'Enter at least two characters.'
            : 'Select at least one available provider.';
        }
        return;
      }

      resultsNode.innerHTML = '';
      if (summaryNode) {
        summaryNode.textContent = '';
      }
      if (statusNode) {
        statusNode.textContent = 'Searching ' + providers.length + ' providers…';
      }

      var completed = 0;
      var totalResults = 0;
      var failures = 0;

      Promise.all(
        providers.map(function (provider) {
          return request('sc_library_v260_search_provider', {
            provider: provider,
            query: query,
            limit: limit
          }).then(function (payload) {
            completed += 1;
            totalResults += parseInt(payload.result_count || 0, 10);
            resultsNode.appendChild(renderProviderGroup(payload));
            if (statusNode) {
              statusNode.textContent = completed + ' of ' + providers.length + ' providers complete.';
            }
            return payload;
          }).catch(function (error) {
            completed += 1;
            failures += 1;
            var section = make('section', 'sc-connector-provider-results is-error');
            section.appendChild(make('h2', '', provider));
            section.appendChild(make('p', '', error.message));
            resultsNode.appendChild(section);
            if (statusNode) {
              statusNode.textContent = completed + ' of ' + providers.length + ' providers complete.';
            }
            return null;
          });
        })
      ).then(function () {
        if (statusNode) {
          statusNode.textContent = strings.complete || 'Search complete.';
        }
        if (summaryNode) {
          summaryNode.textContent =
            totalResults + ' normalized results across ' +
            providers.length + ' providers' +
            (failures ? ' · ' + failures + ' provider failures' : '') +
            '.';
        }
      });
    });

    resultsNode.addEventListener('click', function (event) {
      var button = event.target.closest('[data-sc-import-token]');
      if (!button) {
        return;
      }
      event.preventDefault();
      var card = button.closest('.sc-connector-result-card');
      var status = card ? card.querySelector('.sc-connector-result-card__import-status') : null;
      button.disabled = true;
      if (status) {
        status.textContent = strings.importing || 'Importing source…';
      }

      if (!button.dataset.scIdempotencyKey) {
        button.dataset.scIdempotencyKey =
          (window.crypto && typeof window.crypto.randomUUID === 'function')
            ? window.crypto.randomUUID()
            : 'import-' + Date.now() + '-' + Math.random().toString(36).slice(2);
      }

      request('sc_library_v260_import_result', {
        token: button.dataset.scImportToken,
        mode: 'fill_empty',
        project_id: config.projectId || 0,
        idempotency_key: button.dataset.scIdempotencyKey
      }).then(function (payload) {
        if (status) {
          status.textContent =
            (payload.idempotent_replay ? 'Recovered previous import result. ' : '') +
            (payload.message || strings.imported || 'Source imported.') +
            (payload.open_conflict_count ? ' ' + payload.open_conflict_count + ' metadata conflict(s) need review.' : '');
        }
        button.textContent = 'Imported';
        if (payload.edit_url) {
          var edit = make('a', 'button', 'Review Source');
          edit.href = payload.edit_url;
          button.parentNode.insertBefore(edit, button.nextSibling);
        }
      }).catch(function (error) {
        button.disabled = false;
        if (status) {
          status.textContent = error.message;
        }
      });
    });
  });

  document.querySelectorAll('[data-sc-source-locator]').forEach(function (root) {
    var button = root.querySelector('[data-sc-locate-source]');
    var status = root.querySelector('[data-sc-locator-status]');
    var results = root.querySelector('[data-sc-locator-results]');
    if (!button || !results) {
      return;
    }

    button.addEventListener('click', function () {
      button.disabled = true;
      status.textContent = strings.locating || 'Checking source locations…';
      results.innerHTML = '';

      request('sc_library_v260_locate_source', {
        source_id: root.dataset.sourceId
      }).then(function (payload) {
        var list = make('ul', 'sc-source-locator-list');
        (payload.locations || []).forEach(function (location) {
          var item = make('li', '');
          var link = make('a', '', location.label || location.provider || 'Open');
          link.href = location.url;
          link.target = '_blank';
          link.rel = 'noopener';
          item.appendChild(link);
          if (location.status) {
            item.appendChild(make('small', '', location.status.replace(/-/g, ' ')));
          }
          list.appendChild(item);
        });
        results.appendChild(list);
        status.textContent =
          (payload.locations || []).length +
          ' location or discovery action' +
          ((payload.locations || []).length === 1 ? '' : 's') +
          ' available.';
      }).catch(function (error) {
        status.textContent = error.message;
      }).finally(function () {
        button.disabled = false;
      });
    });
  });

  document.querySelectorAll('[data-sc-test-provider]').forEach(function (button) {
    button.addEventListener('click', function () {
      var card = button.closest('[data-provider-card]');
      var status = card ? card.querySelector('[data-provider-test-status]') : null;
      button.disabled = true;
      if (status) {
        status.textContent = 'Testing provider…';
      }
      request('sc_library_v260_test_provider', {
        provider: button.dataset.scTestProvider
      }).then(function (payload) {
        if (status) {
          status.textContent =
            (payload.message || 'Provider test succeeded.') +
            ' ' + (payload.duration_ms || 0) + ' ms.';
        }
      }).catch(function (error) {
        if (status) {
          status.textContent = error.message;
        }
      }).finally(function () {
        button.disabled = false;
      });
    });
  });

  document.querySelectorAll('[data-sc-connector-settings-form]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var data = new FormData(form);
      var values = {
        contact_email: data.get('contact_email') || '',
        openalex_api_key: data.get('openalex_api_key') || '',
        google_books_api_key: data.get('google_books_api_key') || '',
        ncbi_api_key: data.get('ncbi_api_key') || '',
        ncbi_tool: data.get('ncbi_tool') || '',
        cache_ttl: data.get('cache_ttl') || '43200',
        enabled_providers: data.getAll('enabled_providers[]')
      };
      var status = form.querySelector('[data-sc-settings-status]');
      request('sc_library_v260_save_settings', values).then(function (payload) {
        if (status) {
          status.textContent = payload.message || strings.settingsSaved || 'Settings saved.';
        }
      }).catch(function (error) {
        if (status) {
          status.textContent = error.message;
        }
      });
    });
  });
})();
