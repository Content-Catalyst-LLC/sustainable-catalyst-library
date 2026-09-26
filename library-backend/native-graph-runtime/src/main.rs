use std::collections::{HashMap, HashSet, VecDeque};
use std::env;
use std::fs;
use std::process;

const CONTRACT: &str = "sc-library-native-graph-runtime/1.0";
const VERSION: &str = "0.1.0";

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
    from_id: String,
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
    args.iter().position(|x| x == name)
        .and_then(|i| args.get(i + 1))
        .cloned()
        .unwrap_or_else(|| default.to_string())
}

fn split_csv(value: &str) -> Vec<String> {
    value.split(',').map(str::trim).filter(|x| !x.is_empty()).map(str::to_string).collect()
}

fn read_nodes(path: &str) -> (Vec<String>, HashMap<String, String>) {
    let text = fs::read_to_string(path).unwrap_or_else(|_| fail("unable to read node input"));
    let mut ids = Vec::new();
    let mut kinds = HashMap::new();
    for line in text.lines() {
        if line.trim().is_empty() { continue; }
        let mut parts = line.splitn(2, '\t');
        let id = parts.next().unwrap_or("").trim().to_string();
        let kind = parts.next().unwrap_or("").trim().to_string();
        if id.is_empty() { continue; }
        ids.push(id.clone());
        kinds.insert(id, kind);
    }
    (ids, kinds)
}

fn read_edges(path: &str) -> Vec<Edge> {
    let text = fs::read_to_string(path).unwrap_or_else(|_| fail("unable to read edge input"));
    let mut out = Vec::new();
    for line in text.lines() {
        if line.trim().is_empty() { continue; }
        let parts: Vec<&str> = line.split('\t').collect();
        if parts.len() < 5 { continue; }
        let index = parts[0].parse::<usize>().unwrap_or_else(|_| fail("invalid edge index"));
        out.push(Edge {
            index,
            source: parts[1].to_string(),
            target: parts[2].to_string(),
            basis: parts[3].to_string(),
            directed: parts[4] == "1",
        });
    }
    out
}

fn status() {
    println!("STATUS\t{}\t{}\trust\tstd-only\tpathfinding-foundation", CONTRACT, VERSION);
}

fn pathfind(args: &[String]) {
    let nodes_path = arg_value(args, "--nodes", "");
    let edges_path = arg_value(args, "--edges", "");
    if nodes_path.is_empty() || edges_path.is_empty() { fail("--nodes and --edges are required"); }
    let starts = split_csv(&arg_value(args, "--starts", ""));
    let targets: HashSet<String> = split_csv(&arg_value(args, "--targets", "")).into_iter().collect();
    let target_kinds: HashSet<String> = split_csv(&arg_value(args, "--target-kinds", "publication")).into_iter().collect();
    let max_hops: usize = arg_value(args, "--max-hops", "4").parse().unwrap_or(4).clamp(1, 8);
    let max_paths: usize = arg_value(args, "--max-paths", "12").parse().unwrap_or(12).clamp(1, 50);
    let direction = arg_value(args, "--direction", "both");

    let (node_ids, kinds) = read_nodes(&nodes_path);
    let node_set: HashSet<String> = node_ids.into_iter().collect();
    let edges = read_edges(&edges_path);
    let mut adjacency: HashMap<String, Vec<Step>> = HashMap::new();
    for edge in edges.iter() {
        if !node_set.contains(&edge.source) || !node_set.contains(&edge.target) { continue; }
        if !edge.directed {
            adjacency.entry(edge.source.clone()).or_default().push(Step { from_id: edge.source.clone(), to_id: edge.target.clone(), edge_index: edge.index, basis: edge.basis.clone(), direction: "undirected" });
            adjacency.entry(edge.target.clone()).or_default().push(Step { from_id: edge.target.clone(), to_id: edge.source.clone(), edge_index: edge.index, basis: edge.basis.clone(), direction: "undirected" });
        } else {
            if direction == "both" || direction == "forward" {
                adjacency.entry(edge.source.clone()).or_default().push(Step { from_id: edge.source.clone(), to_id: edge.target.clone(), edge_index: edge.index, basis: edge.basis.clone(), direction: "forward" });
            }
            if direction == "both" || direction == "reverse" {
                adjacency.entry(edge.target.clone()).or_default().push(Step { from_id: edge.target.clone(), to_id: edge.source.clone(), edge_index: edge.index, basis: edge.basis.clone(), direction: "reverse" });
            }
        }
    }
    for steps in adjacency.values_mut() {
        steps.sort_by(|a,b| (a.basis.as_str(), a.to_id.as_str(), a.edge_index).cmp(&(b.basis.as_str(), b.to_id.as_str(), b.edge_index)));
    }

    println!("META\t{}\t{}", CONTRACT, VERSION);
    let mut emitted = 0usize;
    let mut signatures: HashSet<String> = HashSet::new();
    'sources: for source in starts.iter() {
        if !node_set.contains(source) { continue; }
        let mut queue: VecDeque<(String, Vec<String>, Vec<Step>)> = VecDeque::new();
        queue.push_back((source.clone(), vec![source.clone()], Vec::new()));
        let mut best_depth: HashMap<String, usize> = HashMap::new();
        best_depth.insert(source.clone(), 0);
        while let Some((current, node_path, step_path)) = queue.pop_front() {
            if step_path.len() >= max_hops { continue; }
            for step in adjacency.get(&current).cloned().unwrap_or_default() {
                if node_path.contains(&step.to_id) { continue; }
                let mut new_nodes = node_path.clone();
                new_nodes.push(step.to_id.clone());
                let mut new_steps = step_path.clone();
                new_steps.push(step.clone());
                let target_match = step.to_id.as_str() != source.as_str() && (
                    (!targets.is_empty() && targets.contains(&step.to_id)) ||
                    (targets.is_empty() && target_kinds.contains(kinds.get(&step.to_id).map(String::as_str).unwrap_or("")))
                );
                if target_match {
                    let sig = format!("{}|{}", new_nodes.join(","), new_steps.iter().map(|x| x.basis.as_str()).collect::<Vec<_>>().join(","));
                    if signatures.insert(sig) {
                        let step_tokens = new_steps.iter().map(|x| format!("{}:{}", x.edge_index, x.direction)).collect::<Vec<_>>().join(",");
                        println!("PATH\t{}\t{}\t{}", source, step.to_id, step_tokens);
                        emitted += 1;
                        if emitted >= max_paths { break 'sources; }
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

fn main() {
    let args: Vec<String> = env::args().collect();
    match args.get(1).map(String::as_str) {
        Some("status") | Some("--version") => status(),
        Some("pathfind") => pathfind(&args[2..]),
        _ => fail("usage: sc-library-graph-runtime status | pathfind ..."),
    }
}
