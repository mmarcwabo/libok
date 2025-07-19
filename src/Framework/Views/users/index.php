<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="h3">User Management</h2>
    <a href="/users/create" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> Add New User
    </a>
</div>

<div class="card">
    <div class="card-body">
        <table class="table table-hover">
            <thead class="table-light">
                <tr>
                    <th scope="col">Name</th>
                    <th scope="col">Email</th>
                    <th scope="col">Joined On</th>
                    <th scope="col" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= htmlspecialchars($user->name) ?></td>
                    <td><?= htmlspecialchars($user->email) ?></td>
                    <td><?= htmlspecialchars($user->createdAt) ?></td>
                    <td class="text-end">
                        <a href="/users/edit?id=<?= $user->id ?>" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-pencil-fill"></i> Edit
                        </a>
                        <form action="/users/delete" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this user?');">
                            <input type="hidden" name="id" value="<?= $user->id ?>">
                            <button type="submit" class="btn btn-sm btn-outline-danger" <?= ($user->id === $_SESSION['user_id']) ? 'disabled' : '' ?>>
                                <i class="bi bi-trash-fill"></i> Delete
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../layout/footer.php'; ?>