
// src/controllers/enter_observationController.js
exports.getAll = (req, res) => {
  res.json({ message: "GET all enter_observation" });
};

exports.getOne = (req, res) => {
  res.json({ message: "GET enter_observation by ID", id: req.params.id });
};

exports.create = (req, res) => {
  res.json({ message: "POST new enter_observation", body: req.body });
};

exports.update = (req, res) => {
  res.json({ message: "PUT update enter_observation", id: req.params.id, body: req.body });
};

exports.delete = (req, res) => {
  res.json({ message: "DELETE enter_observation", id: req.params.id });
};
