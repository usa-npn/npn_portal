
// src/routes/create_user.js
const express = require('express');
const router = express.Router();
const controller = require('../controllers/create_userController');

router.get('/', controller.getAll);
router.get('/:id', controller.getOne);
router.post('/', controller.create);
router.put('/:id', controller.update);
router.delete('/:id', controller.delete);

module.exports = router;
