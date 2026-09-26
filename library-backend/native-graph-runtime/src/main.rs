use std::collections::{BTreeMap, BTreeSet, HashMap, HashSet, VecDeque};
use std::env;
use std::fs;
use std::process;

const CONTRACT: &str = "sc-library-native-graph-runtime/1.0";
const VERSION: &str = "0.2.0";

#[derive(Clone, Debug)]
struct Edge {
    index: usize,
    source: String,
    target: String,
    basis: String,
    directed: bool,
}

#[derive(Clone, Debug)]
struct Step {
    to_id: String,
    edge_index: usize,
    basis: String,
    direction: &'static str,
}

fn fail(msg: &str) -> ! {
    eprintln!("ERROR\t{}", msg.replace('\t', " ").replace('\n', " "));
    process::exit(2);
}

fn arg_value(args: &[String], name: &str, default: &str) -> String {
    args.iter()
        .position(|x| x == name)
        .and_then(|i| args.get(i + 1))
        .cloned()
        .unwrap_or_else(|| default.to_string())
}

fn split_csv(value: &str) -> Vec<String> {
    value
        .split(',')
        .map(str::trim)
        .filter(|x| !x.is_empty())
        .map(str::to_string)
        .collect()
}

fn read_nodes(path: &str) -> (Vec<String>, HashMap<String, String>) {
    let text = fs::read_to_string(path).unwrap_or_else(|_| fail("unable to read node input"));
    let mut ids = Vec::new();
    let mut kinds = HashMap::new();
    for line in text.lines() {
        if line.trim().is_empty() {
            continue;
        }
        let mut parts = line.splitn(2, '\t');
        let id = parts.next().unwrap_or("").trim().to_string();
        let kind = parts.next().unwrap_or("").trim().to_string();
        if id.is_empty() {
            continue;
        }
        ids.push(id.clone());
        kinds.insert(id, kind);
    }
    ids.sort();
    ids.dedup();
    (ids, kinds)
}

fn read_edges(path: &str) -> Vec<Edge> {
    let text = fs::read_to_string(path).unwrap_or_else(|_| fail("unable to read edge input"));
    let mut out = Vec::new();
    for line in text.lines() {
        if line.trim().is_empty() {
            continue;
        }
        let parts: Vec<&str> = line.split('\t').collect();
        if parts.len() < 5 {
            continue;
        }
        let index = parts[0]
            .parse::<usize>()
            .unwrap_or_else(|_| fail("invalid edge index"));
        out.push(Edge {
            index,
            source: parts[1].to_string(),
            target: parts[2].to_string(),
            basis: parts[3].to_string(),
            directed: parts[4] == "1",
        });
    }
    out.sort_by_key(|x| x.index);
    out
}

fn status() {
    println!(
        "STATUS\t{}\t{}\trust\tstd-only\tbounded-pathfinding,neighborhood,reachability,connected-components,subgraph,structural-stats",
        CONTRACT, VERSION
    );
}

fn build_adjacency(
    node_set: &HashSet<String>,
    edges: &[Edge],
    direction: &str,
) -> HashMap<String, Vec<Step>> {
    let mut adjacency: HashMap<String, Vec<Step>> = HashMap::new();
    for edge in edges.iter() {
        if !node_set.contains(&edge.source) || !node_set.contains(&edge.target) {
            continue;
        }
        if !edge.directed {
            adjacency.entry(edge.source.clone()).or_default().push(Step {
                to_id: edge.target.clone(),
                edge_index: edge.index,
                basis: edge.basis.clone(),
                direction: "undirected",
            });
            adjacency.entry(edge.target.clone()).or_default().push(Step {
                to_id: edge.source.clone(),
                edge_index: edge.index,
                basis: edge.basis.clone(),
                direction: "undirected",
            });
        } else {
            if direction == "both" || direction == "forward" {
                adjacency.entry(edge.source.clone()).or_default().push(Step {
                    to_id: edge.target.clone(),
                    edge_index: edge.index,
                    basis: edge.basis.clone(),
                    direction: "forward",
                });
            }
            if direction == "both" || direction == "reverse" {
                adjacency.entry(edge.target.clone()).or_default().push(Step {
                    to_id: edge.source.clone(),
                    edge_index: edge.index,
                    basis: edge.basis.clone(),
                    direction: "reverse",
                });
            }
        }
    }
    for steps in adjacency.values_mut() {
        steps.sort_by(|a, b| {
            (a.basis.as_str(), a.to_id.as_str(), a.edge_index)
                .cmp(&(b.basis.as_str(), b.to_id.as_str(), b.edge_index))
        });
    }
    adjacency
}

fn bfs_depths(
    starts: &[String],
    adjacency: &HashMap<String, Vec<Step>>,
    max_depth: usize,
    max_nodes: usize,
    node_set: &HashSet<String>,
) -> BTreeMap<String, usize> {
    let mut depths: BTreeMap<String, usize> = BTreeMap::new();
    let mut queue: VecDeque<String> = VecDeque::new();
    for start in starts.iter() {
        if node_set.contains(start) && !depths.contains_key(start) {
            depths.insert(start.clone(), 0);
            queue.push_back(start.clone());
        }
    }
    while let Some(current) = queue.pop_front() {
        let depth = *depths.get(&current).unwrap_or(&0);
        if depth >= max_depth || depths.len() >= max_nodes {
            continue;
        }
        for step in adjacency.get(&current).cloned().unwrap_or_default() {
            if depths.len() >= max_nodes {
                break;
            }
            if !depths.contains_key(&step.to_id) {
                depths.insert(step.to_id.clone(), depth + 1);
                queue.push_back(step.to_id);
            }
        }
    }
    depths
}

fn induced_edge_indexes(edges: &[Edge], nodes: &BTreeSet<String>, max_edges: usize) -> Vec<usize> {
    let mut indexes: Vec<usize> = edges
        .iter()
        .filter(|e| nodes.contains(&e.source) && nodes.contains(&e.target))
        .map(|e| e.index)
        .collect();
    indexes.sort_unstable();
    indexes.dedup();
    indexes.truncate(max_edges);
    indexes
}

fn pathfind(args: &[String]) {
    let nodes_path = arg_value(args, "--nodes", "");
    let edges_path = arg_value(args, "--edges", "");
    if nodes_path.is_empty() || edges_path.is_empty() {
        fail("--nodes and --edges are required");
    }
    let starts = split_csv(&arg_value(args, "--starts", ""));
    let targets: HashSet<String> = split_csv(&arg_value(args, "--targets", ""))
        .into_iter()
        .collect();
    let target_kinds: HashSet<String> = split_csv(&arg_value(args, "--target-kinds", "publication"))
        .into_iter()
        .collect();
    let max_hops: usize = arg_value(args, "--max-hops", "4")
        .parse()
        .unwrap_or(4)
        .clamp(1, 8);
    let max_paths: usize = arg_value(args, "--max-paths", "12")
        .parse()
        .unwrap_or(12)
        .clamp(1, 50);
    let direction = arg_value(args, "--direction", "both");

    let (node_ids, kinds) = read_nodes(&nodes_path);
    let node_set: HashSet<String> = node_ids.into_iter().collect();
    let edges = read_edges(&edges_path);
    let adjacency = build_adjacency(&node_set, &edges, &direction);

    println!("META\t{}\t{}", CONTRACT, VERSION);
    let mut emitted = 0usize;
    let mut signatures: HashSet<String> = HashSet::new();
    'sources: for source in starts.iter() {
        if !node_set.contains(source) {
            continue;
        }
        let mut queue: VecDeque<(String, Vec<String>, Vec<Step>)> = VecDeque::new();
        queue.push_back((source.clone(), vec![source.clone()], Vec::new()));
        let mut best_depth: HashMap<String, usize> = HashMap::new();
        best_depth.insert(source.clone(), 0);
        while let Some((current, node_path, step_path)) = queue.pop_front() {
            if step_path.len() >= max_hops {
                continue;
            }
            for step in adjacency.get(&current).cloned().unwrap_or_default() {
                if node_path.contains(&step.to_id) {
                    continue;
                }
                let mut new_nodes = node_path.clone();
                new_nodes.push(step.to_id.clone());
                let mut new_steps = step_path.clone();
                new_steps.push(step.clone());
                let target_match = step.to_id.as_str() != source.as_str()
                    && ((!targets.is_empty() && targets.contains(&step.to_id))
                        || (targets.is_empty()
                            && target_kinds.contains(
                                kinds.get(&step.to_id).map(String::as_str).unwrap_or(""),
                            )));
                if target_match {
                    let sig = format!(
                        "{}|{}",
                        new_nodes.join(","),
                        new_steps
                            .iter()
                            .map(|x| x.basis.as_str())
                            .collect::<Vec<_>>()
                            .join(",")
                    );
                    if signatures.insert(sig) {
                        let step_tokens = new_steps
                            .iter()
                            .map(|x| format!("{}:{}", x.edge_index, x.direction))
                            .collect::<Vec<_>>()
                            .join(",");
                        println!("PATH\t{}\t{}\t{}", source, step.to_id, step_tokens);
                        emitted += 1;
                        if emitted >= max_paths {
                            break 'sources;
                        }
                    }
                }
                let nd = new_steps.len();
                let prior = best_depth.get(&step.to_id).copied();
                if prior.map(|p| nd <= p + 1).unwrap_or(true) {
                    best_depth.insert(step.to_id.clone(), prior.map(|p| p.min(nd)).unwrap_or(nd));
                    queue.push_back((step.to_id.clone(), new_nodes, new_steps));
                }
            }
        }
    }
}

fn native_query(args: &[String]) {
    let nodes_path = arg_value(args, "--nodes", "");
    let edges_path = arg_value(args, "--edges", "");
    if nodes_path.is_empty() || edges_path.is_empty() {
        fail("--nodes and --edges are required");
    }
    let operation = arg_value(args, "--operation", "neighborhood");
    let starts = split_csv(&arg_value(args, "--starts", ""));
    let selected_ids: BTreeSet<String> = split_csv(&arg_value(args, "--node-ids", ""))
        .into_iter()
        .collect();
    let max_depth: usize = arg_value(args, "--max-depth", "2")
        .parse()
        .unwrap_or(2)
        .clamp(0, 8);
    let max_nodes: usize = arg_value(args, "--max-nodes", "1000")
        .parse()
        .unwrap_or(1000)
        .clamp(1, 100000);
    let max_edges: usize = arg_value(args, "--max-edges", "5000")
        .parse()
        .unwrap_or(5000)
        .clamp(1, 250000);
    let direction = arg_value(args, "--direction", "both");

    let (node_ids, _kinds) = read_nodes(&nodes_path);
    let node_set: HashSet<String> = node_ids.iter().cloned().collect();
    let edges = read_edges(&edges_path);
    let adjacency = build_adjacency(&node_set, &edges, &direction);

    println!("META\t{}\t{}\t{}", CONTRACT, VERSION, operation);

    match operation.as_str() {
        "neighborhood" => {
            let depths = bfs_depths(&starts, &adjacency, max_depth, max_nodes, &node_set);
            let seen: BTreeSet<String> = depths.keys().cloned().collect();
            for (id, depth) in depths.iter() {
                println!("NODE\t{}\t{}", id, depth);
            }
            for edge_index in induced_edge_indexes(&edges, &seen, max_edges) {
                println!("EDGE\t{}", edge_index);
            }
        }
        "reachability" => {
            let depths = bfs_depths(&starts, &adjacency, max_depth, max_nodes, &node_set);
            for (id, depth) in depths.iter() {
                println!("REACH\t{}\t{}", id, depth);
            }
        }
        "subgraph" => {
            let selected: BTreeSet<String> = if selected_ids.is_empty() {
                node_ids.iter().take(max_nodes).cloned().collect()
            } else {
                selected_ids
                    .into_iter()
                    .filter(|id| node_set.contains(id))
                    .take(max_nodes)
                    .collect()
            };
            for id in selected.iter() {
                println!("NODE\t{}\t0", id);
            }
            for edge_index in induced_edge_indexes(&edges, &selected, max_edges) {
                println!("EDGE\t{}", edge_index);
            }
        }
        "connected-components" => {
            let scope: BTreeSet<String> = if selected_ids.is_empty() {
                node_ids.iter().take(max_nodes).cloned().collect()
            } else {
                selected_ids
                    .into_iter()
                    .filter(|id| node_set.contains(id))
                    .take(max_nodes)
                    .collect()
            };
            let mut undirected: HashMap<String, Vec<String>> = HashMap::new();
            for edge in edges.iter() {
                if !scope.contains(&edge.source) || !scope.contains(&edge.target) {
                    continue;
                }
                undirected.entry(edge.source.clone()).or_default().push(edge.target.clone());
                undirected.entry(edge.target.clone()).or_default().push(edge.source.clone());
            }
            for vals in undirected.values_mut() {
                vals.sort();
                vals.dedup();
            }
            let mut seen: HashSet<String> = HashSet::new();
            let mut component_index = 0usize;
            for root in scope.iter() {
                if seen.contains(root) {
                    continue;
                }
                component_index += 1;
                let mut queue: VecDeque<String> = VecDeque::new();
                let mut members: Vec<String> = Vec::new();
                seen.insert(root.clone());
                queue.push_back(root.clone());
                while let Some(current) = queue.pop_front() {
                    members.push(current.clone());
                    for next in undirected.get(&current).cloned().unwrap_or_default() {
                        if !seen.contains(&next) {
                            seen.insert(next.clone());
                            queue.push_back(next);
                        }
                    }
                }
                members.sort();
                for id in members {
                    println!("COMPONENT\t{}\t{}", component_index, id);
                }
            }
        }
        "structural-stats" => {
            let scope: BTreeSet<String> = if selected_ids.is_empty() {
                node_ids.iter().take(max_nodes).cloned().collect()
            } else {
                selected_ids
                    .into_iter()
                    .filter(|id| node_set.contains(id))
                    .take(max_nodes)
                    .collect()
            };
            let scoped_edges: Vec<&Edge> = edges
                .iter()
                .filter(|e| scope.contains(&e.source) && scope.contains(&e.target))
                .take(max_edges)
                .collect();
            let mut degree: BTreeMap<String, usize> = scope.iter().map(|x| (x.clone(), 0)).collect();
            let mut directed = 0usize;
            let mut undirected = 0usize;
            for edge in scoped_edges.iter() {
                if edge.directed {
                    directed += 1;
                } else {
                    undirected += 1;
                }
                *degree.entry(edge.source.clone()).or_insert(0) += 1;
                *degree.entry(edge.target.clone()).or_insert(0) += 1;
            }
            let isolated = degree.values().filter(|v| **v == 0).count();
            let max_degree = degree.values().copied().max().unwrap_or(0);
            let total_degree: usize = degree.values().copied().sum();
            let avg_degree_x1000 = if scope.is_empty() {
                0
            } else {
                (total_degree * 1000) / scope.len()
            };

            let mut component_adj: HashMap<String, Vec<String>> = HashMap::new();
            for edge in scoped_edges.iter() {
                component_adj.entry(edge.source.clone()).or_default().push(edge.target.clone());
                component_adj.entry(edge.target.clone()).or_default().push(edge.source.clone());
            }
            let mut comp_seen: HashSet<String> = HashSet::new();
            let mut component_count = 0usize;
            for root in scope.iter() {
                if comp_seen.contains(root) {
                    continue;
                }
                component_count += 1;
                let mut queue = VecDeque::new();
                comp_seen.insert(root.clone());
                queue.push_back(root.clone());
                while let Some(current) = queue.pop_front() {
                    for next in component_adj.get(&current).cloned().unwrap_or_default() {
                        if !comp_seen.contains(&next) {
                            comp_seen.insert(next.clone());
                            queue.push_back(next);
                        }
                    }
                }
            }

            println!("STAT\tnode_count\t{}", scope.len());
            println!("STAT\tedge_count\t{}", scoped_edges.len());
            println!("STAT\tdirected_edge_count\t{}", directed);
            println!("STAT\tundirected_edge_count\t{}", undirected);
            println!("STAT\tisolated_node_count\t{}", isolated);
            println!("STAT\tcomponent_count\t{}", component_count);
            println!("STAT\tmax_degree\t{}", max_degree);
            println!("STAT\taverage_degree_milli\t{}", avg_degree_x1000);
        }
        _ => fail("unsupported --operation"),
    }
}

fn main() {
    let args: Vec<String> = env::args().collect();
    match args.get(1).map(String::as_str) {
        Some("status") | Some("--version") => status(),
        Some("pathfind") => pathfind(&args[2..]),
        Some("query") => native_query(&args[2..]),
        _ => fail("usage: sc-library-graph-runtime status | pathfind ... | query --operation ..."),
    }
}
