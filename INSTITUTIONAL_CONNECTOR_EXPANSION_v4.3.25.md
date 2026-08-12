# Institutional Connector Expansion — v4.3.25

This release adds a capability-labeled institutional research network above the existing connector layer. Institutions are classified as Direct Connector, Open Repository, Standards-Capable, or Research Gateway. The classification prevents public catalog discovery from being confused with licensed-user entitlement.

The network exposes search templates and repository routes for MIT, Harvard, UC Berkeley/eScholarship, UCD, Stanford, Yale, Princeton, Columbia, Copenhagen, Stockholm, Wageningen, Lund, ETH Zürich, Oxford, Cambridge, IIASA, Stockholm Environment Institute, and United Nations University.

The REST endpoint `sc-library/v1/institutional-connectors?q=` returns the normalized network and resolved search URLs. No external-library credentials are stored. Existing My Libraries, Access Intelligence, Citation Studio, Document Builder, and Publications behavior remains intact.
